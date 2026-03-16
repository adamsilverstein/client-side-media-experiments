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
	var CDN_INTEGRITY = window.csmeHeicSupport.cdnIntegrity || '';
	var JPEG_QUALITY = parseFloat( window.csmeHeicSupport.jpegQuality ) || 0.92;
	var heic2anyPromise = null;
	var NOTICE_ID = 'csme-heic-converting';
	var nativeHeicSupport = null; // null = untested, true/false = result

	/**
	 * Tests whether the browser can natively decode HEIC images.
	 *
	 * Creates a tiny blob with image/heic MIME type and attempts to
	 * decode it via createImageBitmap. Safari supports this natively.
	 *
	 * @return {Promise<boolean>} Resolves true if native HEIC is supported.
	 */
	function checkNativeHeicSupport() {
		if ( nativeHeicSupport !== null ) {
			return Promise.resolve( nativeHeicSupport );
		}

		if ( typeof createImageBitmap === 'undefined' ) {
			nativeHeicSupport = false;
			return Promise.resolve( false );
		}

		return new Promise( function ( resolve ) {
			// 1x1 pixel HEIC: attempt to decode a small HEIC blob.
			// Browsers without native HEIC support will reject.
			var blob = new Blob(
				[
					new Uint8Array( [
						0x00, 0x00, 0x00, 0x24, 0x66, 0x74, 0x79, 0x70,
						0x68, 0x65, 0x69, 0x63, 0x00, 0x00, 0x00, 0x00,
						0x68, 0x65, 0x69, 0x63, 0x68, 0x65, 0x69, 0x78,
						0x6d, 0x69, 0x66, 0x31, 0x4d, 0x69, 0x48, 0x45,
						0x68, 0x65, 0x76, 0x63,
					] ),
				],
				{ type: 'image/heic' }
			);
			createImageBitmap( blob )
				.then( function () {
					nativeHeicSupport = true;
					resolve( true );
				} )
				.catch( function () {
					nativeHeicSupport = false;
					resolve( false );
				} );
		} );
	}

	/**
	 * Shows an info notice while HEIC conversion is in progress.
	 */
	function showConversionNotice() {
		try {
			wp.data
				.dispatch( 'core/notices' )
				.createInfoNotice( 'Converting HEIC image(s) to JPEG\u2026', {
					id: NOTICE_ID,
					isDismissible: false,
				} );
		} catch ( e ) {
			// Notices store may not be available.
		}
	}

	/**
	 * Removes the HEIC conversion notice.
	 */
	function removeConversionNotice() {
		try {
			wp.data.dispatch( 'core/notices' ).removeNotice( NOTICE_ID );
		} catch ( e ) {
			// Notices store may not be available.
		}
	}

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
			if ( CDN_INTEGRITY ) {
				script.integrity = CDN_INTEGRITY;
				script.crossOrigin = 'anonymous';
			}
			script.onload = function () {
				if ( window.heic2any ) {
					resolve( window.heic2any );
				} else {
					reject(
						new Error(
							'heic2any was not available after loading the script. ' +
								'The CDN may be serving an unexpected response.'
						)
					);
				}
			};
			script.onerror = function () {
				heic2anyPromise = null;
				reject(
					new Error(
						'Failed to load HEIC conversion library from CDN. ' +
							'Check your network connection or ask your site ' +
							'administrator to host the library locally using ' +
							'the csme_heic_library_url filter.'
					)
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
					quality: JPEG_QUALITY,
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

			// Skip conversion if the browser handles HEIC natively (e.g. Safari).
			checkNativeHeicSupport().then( function ( supported ) {
				if ( supported ) {
					return originalMediaUpload( args );
				}

				showConversionNotice();

				Promise.all(
					files.map( function ( file ) {
						if ( isHeicFile( file ) ) {
							return convertHeicToJpeg( file ).catch(
								function ( error ) {
									if ( args.onError ) {
										args.onError(
											new Error(
												'HEIC to JPEG conversion failed for "' +
													file.name +
													'": ' +
													( error && error.message
														? error.message
														: String( error ) )
											)
										);
									}
									return null;
								}
							);
						}
						return Promise.resolve( file );
					} )
				).then( function ( convertedFiles ) {
					removeConversionNotice();
					var successfulFiles = convertedFiles.filter(
						function ( file ) {
							return file !== null;
						}
					);
					if ( successfulFiles.length === 0 ) {
						return;
					}
					var newArgs = {};
					for ( var key in args ) {
						if ( args.hasOwnProperty( key ) ) {
							newArgs[ key ] = args[ key ];
						}
					}
					newArgs.filesList = successfulFiles;
					originalMediaUpload( newArgs );
				} );
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
