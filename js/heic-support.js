/**
 * HEIC/HEIF upload support for the block editor.
 *
 * Intercepts file uploads, converts HEIC/HEIF files to JPEG using
 * a dynamically loaded library, and passes the converted files to
 * the standard upload handler.
 *
 * The heic2any library (which uses libheif, LGPL-3.0 licensed) is
 * loaded from an external CDN only when a HEIC file is detected.
 */

/* global wp, csmeHeicSupport */

( function () {
	'use strict';

	if ( ! window.csmeHeicSupport || ! window.csmeHeicSupport.cdnUrl ) {
		return;
	}

	var CDN_URL = window.csmeHeicSupport.cdnUrl;
	var heic2anyPromise = null;

	/**
	 * Checks whether a file is HEIC or HEIF format.
	 *
	 * @param {File} file The file to check.
	 * @return {boolean} True if the file is HEIC/HEIF.
	 */
	function isHeicFile( file ) {
		if ( file.type === 'image/heic' || file.type === 'image/heif' ) {
			return true;
		}
		// Some browsers don't set the MIME type for HEIC files.
		var name = file.name || '';
		return /\.heic$/i.test( name ) || /\.heif$/i.test( name );
	}

	/**
	 * Dynamically loads the heic2any library from CDN.
	 *
	 * @return {Promise} Resolves with the heic2any function.
	 */
	function loadHeic2Any() {
		if ( heic2anyPromise ) {
			return heic2anyPromise;
		}

		heic2anyPromise = new Promise( function ( resolve, reject ) {
			var script = document.createElement( 'script' );
			script.src = CDN_URL;
			script.onload = function () {
				if ( window.heic2any ) {
					resolve( window.heic2any );
				} else {
					reject(
						new Error( 'heic2any not available after loading.' )
					);
				}
			};
			script.onerror = function () {
				heic2anyPromise = null;
				reject(
					new Error( 'Failed to load HEIC conversion library.' )
				);
			};
			document.head.appendChild( script );
		} );

		return heic2anyPromise;
	}

	/**
	 * Converts a single HEIC/HEIF file to JPEG.
	 *
	 * @param {File} file The HEIC file to convert.
	 * @return {Promise<File>} Resolves with the converted JPEG file.
	 */
	function convertHeicToJpeg( file ) {
		return loadHeic2Any()
			.then( function ( heic2any ) {
				return heic2any( {
					blob: file,
					toType: 'image/jpeg',
					quality: 0.92,
				} );
			} )
			.then( function ( result ) {
				var blob = Array.isArray( result ) ? result[ 0 ] : result;
				var newName = file.name
					.replace( /\.heic$/i, '.jpg' )
					.replace( /\.heif$/i, '.jpg' );
				return new File( [ blob ], newName, {
					type: 'image/jpeg',
					lastModified: file.lastModified,
				} );
			} );
	}

	/**
	 * Wraps the block editor's mediaUpload setting to handle HEIC files.
	 *
	 * @return {boolean} True if wrapping succeeded.
	 */
	function wrapMediaUpload() {
		var settings = wp.data.select( 'core/block-editor' ).getSettings();
		var originalMediaUpload = settings.mediaUpload;

		if ( ! originalMediaUpload ) {
			return false;
		}

		function wrappedMediaUpload( args ) {
			if ( ! args.filesList || ! args.filesList.length ) {
				return originalMediaUpload( args );
			}

			var files = Array.prototype.slice.call( args.filesList );
			var hasHeic = files.some( isHeicFile );

			if ( ! hasHeic ) {
				return originalMediaUpload( args );
			}

			Promise.all(
				files.map( function ( file ) {
					if ( isHeicFile( file ) ) {
						return convertHeicToJpeg( file );
					}
					return Promise.resolve( file );
				} )
			)
				.then( function ( convertedFiles ) {
					var newArgs = {};
					for ( var key in args ) {
						if ( args.hasOwnProperty( key ) ) {
							newArgs[ key ] = args[ key ];
						}
					}
					newArgs.filesList = convertedFiles;
					originalMediaUpload( newArgs );
				} )
				.catch( function ( error ) {
					if ( args.onError ) {
						args.onError(
							new Error(
								'HEIC to JPEG conversion failed: ' +
									( error && error.message
										? error.message
										: String( error ) )
							)
						);
					}
				} );
		}

		wrappedMediaUpload.__csmeHeicWrapped = true;

		wp.data
			.dispatch( 'core/block-editor' )
			.updateSettings( { mediaUpload: wrappedMediaUpload } );

		return true;
	}

	/**
	 * Attempts to wrap mediaUpload, returning success status.
	 *
	 * @return {boolean} True if wrapping succeeded.
	 */
	function tryWrap() {
		try {
			var settings = wp.data
				.select( 'core/block-editor' )
				.getSettings();
			if (
				settings.mediaUpload &&
				! settings.mediaUpload.__csmeHeicWrapped
			) {
				return wrapMediaUpload();
			}
		} catch ( e ) {
			// Store may not be initialized yet.
		}
		return false;
	}

	wp.domReady( function () {
		if ( tryWrap() ) {
			return;
		}

		// Subscribe to store changes until mediaUpload becomes available.
		var unsubscribe = wp.data.subscribe( function () {
			if ( tryWrap() ) {
				unsubscribe();
			}
		} );
	} );
} )();
