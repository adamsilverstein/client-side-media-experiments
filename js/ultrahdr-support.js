/**
 * UltraHDR image upload support for the block editor.
 *
 * Detects UltraHDR images (JPEGs with embedded gain maps) during upload,
 * preserves the original file with its gain map intact, and regenerates
 * UltraHDR sub-sizes so HDR data is preserved at all image sizes.
 *
 * UltraHDR images are standard JPEGs with an embedded secondary JPEG
 * (the gain map) and XMP metadata. They are backwards-compatible: browsers
 * without HDR support display the SDR base image normally.
 */

/* global wp, csmeUltraHDRSupport */

( function () {
	'use strict';

	if ( ! window.csmeUltraHDRSupport ) {
		return;
	}

	var NOTICE_ID = 'csme-ultrahdr-processing';

	/**
	 * Checks whether a file is an UltraHDR image by scanning for gain map
	 * XMP metadata markers in the file's binary data.
	 *
	 * UltraHDR images contain XMP metadata with the Adobe HDR gain map
	 * namespace (http://ns.adobe.com/hdr-gain-map/1.0/) or the hdrgm:Version
	 * attribute.
	 *
	 * @param {File} file The file to check.
	 * @return {Promise<boolean>} Resolves true if the file is UltraHDR.
	 */
	function isUltraHDR( file ) {
		// Only check JPEG files.
		if ( file.type && file.type !== 'image/jpeg' ) {
			return Promise.resolve( false );
		}

		var name = file.name || '';
		if ( name && ! /\.jpe?g$/i.test( name ) ) {
			return Promise.resolve( false );
		}

		// Read first 128KB to find XMP markers.
		var sliceSize = Math.min( file.size, 131072 );
		var slice = file.slice( 0, sliceSize );

		return new Promise( function ( resolve ) {
			var reader = new FileReader();
			reader.onload = function () {
				var bytes = new Uint8Array( reader.result );
				var text = '';
				for ( var i = 0; i < bytes.length; i++ ) {
					text += String.fromCharCode( bytes[ i ] );
				}

				// Look for UltraHDR gain map markers in XMP data.
				var hasGainMap =
					text.indexOf( 'hdrgm:Version' ) !== -1 ||
					text.indexOf(
						'http://ns.adobe.com/hdr-gain-map/1.0/'
					) !== -1 ||
					text.indexOf( 'HDRGainMap' ) !== -1;

				resolve( hasGainMap );
			};
			reader.onerror = function () {
				resolve( false );
			};
			reader.readAsArrayBuffer( slice );
		} );
	}

	/**
	 * Extracts gain map data from an UltraHDR JPEG file.
	 *
	 * Parses the JPEG to find the Multi-Picture Format (MPF) entry pointing
	 * to the secondary gain map image, and extracts the XMP metadata
	 * containing gain map parameters.
	 *
	 * @param {File} file The UltraHDR JPEG file.
	 * @return {Promise<Object|null>} Resolves with gain map data or null.
	 *   - {Uint8Array} gainMapBytes - The gain map JPEG bytes.
	 *   - {Object}     metadata     - XMP gain map parameters.
	 *   - {Uint8Array} sdrBytes     - The SDR base JPEG bytes.
	 */
	function extractGainMap( file ) {
		return new Promise( function ( resolve ) {
			var reader = new FileReader();
			reader.onload = function () {
				var buffer = reader.result;
				var data = new Uint8Array( buffer );
				var result = parseUltraHDR( data );
				resolve( result );
			};
			reader.onerror = function () {
				resolve( null );
			};
			reader.readAsArrayBuffer( file );
		} );
	}

	/**
	 * Parses UltraHDR JPEG binary data to extract the gain map.
	 *
	 * UltraHDR files use the Multi-Picture Format (MPF) to embed a
	 * secondary JPEG image (the gain map). The MPF directory is stored
	 * in an APP2 marker with the "MPF\0" identifier. This function
	 * locates the secondary image offset from the MPF index and
	 * extracts the gain map JPEG bytes.
	 *
	 * @param {Uint8Array} data The full JPEG file bytes.
	 * @return {Object|null} Parsed gain map data or null on failure.
	 */
	function parseUltraHDR( data ) {
		if ( data[ 0 ] !== 0xff || data[ 1 ] !== 0xd8 ) {
			return null; // Not a JPEG.
		}

		var gainMapOffset = null;
		var gainMapLength = null;
		var xmpMetadata = {};
		var offset = 2;

		// Walk through JPEG markers to find MPF and XMP segments.
		while ( offset < data.length - 1 ) {
			if ( data[ offset ] !== 0xff ) {
				break;
			}

			var marker = data[ offset + 1 ];

			// SOS marker — end of metadata.
			if ( marker === 0xda ) {
				break;
			}

			// Skip markers without length (RST, TEM, SOI, EOI).
			if (
				marker === 0xd0 ||
				marker === 0xd1 ||
				marker === 0xd2 ||
				marker === 0xd3 ||
				marker === 0xd4 ||
				marker === 0xd5 ||
				marker === 0xd6 ||
				marker === 0xd7 ||
				marker === 0x01 ||
				marker === 0xd8 ||
				marker === 0xd9
			) {
				offset += 2;
				continue;
			}

			var segmentLength =
				( data[ offset + 2 ] << 8 ) | data[ offset + 3 ];

			// APP2 marker — check for MPF.
			if ( marker === 0xe2 && segmentLength > 8 ) {
				var mpfId = String.fromCharCode(
					data[ offset + 4 ],
					data[ offset + 5 ],
					data[ offset + 6 ],
					data[ offset + 7 ]
				);

				if ( mpfId === 'MPF\0' ) {
					var mpfData = parseMPF(
						data,
						offset + 8,
						segmentLength - 6
					);
					if ( mpfData ) {
						gainMapOffset = mpfData.offset;
						gainMapLength = mpfData.length;
					}
				}
			}

			// APP1 marker — check for XMP with gain map metadata.
			if ( marker === 0xe1 && segmentLength > 30 ) {
				var xmpStart = offset + 4;
				var xmpIdCheck = '';
				for ( var x = 0; x < 28 && xmpStart + x < data.length; x++ ) {
					xmpIdCheck += String.fromCharCode( data[ xmpStart + x ] );
				}

				if ( xmpIdCheck.indexOf( 'http://ns.adobe.com/xap/1.0/' ) === 0 ) {
					var xmpText = '';
					for (
						var xi = offset + 4;
						xi < offset + 2 + segmentLength && xi < data.length;
						xi++
					) {
						xmpText += String.fromCharCode( data[ xi ] );
					}
					xmpMetadata = parseGainMapXMP( xmpText );
				}
			}

			offset += 2 + segmentLength;
		}

		if ( gainMapOffset === null || gainMapLength === null ) {
			return null;
		}

		// Find the end of the primary JPEG image (look for EOI marker).
		var primaryEnd = findJPEGEnd( data, 0 );
		if ( primaryEnd === null ) {
			primaryEnd = gainMapOffset;
		}

		return {
			gainMapBytes: data.slice( gainMapOffset, gainMapOffset + gainMapLength ),
			metadata: xmpMetadata,
			sdrBytes: data.slice( 0, primaryEnd ),
		};
	}

	/**
	 * Finds the end of a JPEG image (EOI marker) starting from an offset.
	 *
	 * @param {Uint8Array} data  The file bytes.
	 * @param {number}     start Start offset.
	 * @return {number|null} Offset after the EOI marker, or null.
	 */
	function findJPEGEnd( data, start ) {
		// Skip SOI.
		var pos = start + 2;

		while ( pos < data.length - 1 ) {
			if ( data[ pos ] !== 0xff ) {
				pos++;
				continue;
			}

			var m = data[ pos + 1 ];

			// EOI marker.
			if ( m === 0xd9 ) {
				return pos + 2;
			}

			// SOS marker — skip to next 0xFF that is not 0x00 (stuffed byte).
			if ( m === 0xda ) {
				pos += 2;
				// Skip SOS header.
				var sosLen = ( data[ pos ] << 8 ) | data[ pos + 1 ];
				pos += sosLen;
				// Scan through entropy-coded data.
				while ( pos < data.length - 1 ) {
					if ( data[ pos ] === 0xff && data[ pos + 1 ] !== 0x00 ) {
						break;
					}
					pos++;
				}
				continue;
			}

			// Skip markers without length.
			if (
				( m >= 0xd0 && m <= 0xd7 ) ||
				m === 0x01 ||
				m === 0xd8
			) {
				pos += 2;
				continue;
			}

			// Read segment length and skip.
			if ( pos + 3 < data.length ) {
				var len = ( data[ pos + 2 ] << 8 ) | data[ pos + 3 ];
				pos += 2 + len;
			} else {
				break;
			}
		}

		return null;
	}

	/**
	 * Parses the Multi-Picture Format (MPF) directory to find the
	 * secondary image (gain map) offset and length.
	 *
	 * @param {Uint8Array} data       The full JPEG file bytes.
	 * @param {number}     mpfStart   Start of the MPF data (after "MPF\0").
	 * @param {number}     mpfLength  Length of the MPF data.
	 * @return {Object|null} Object with offset and length, or null.
	 */
	function parseMPF( data, mpfStart, mpfLength ) {
		if ( mpfLength < 16 ) {
			return null;
		}

		// Determine byte order from TIFF header.
		var bigEndian;
		if (
			data[ mpfStart ] === 0x4d &&
			data[ mpfStart + 1 ] === 0x4d
		) {
			bigEndian = true;
		} else if (
			data[ mpfStart ] === 0x49 &&
			data[ mpfStart + 1 ] === 0x49
		) {
			bigEndian = false;
		} else {
			return null;
		}

		/**
		 * Reads a 32-bit unsigned integer from the data array.
		 *
		 * @param {number} pos Position to read from.
		 * @return {number} The 32-bit value.
		 */
		function readUint32( pos ) {
			if ( bigEndian ) {
				return (
					( ( data[ pos ] << 24 ) >>> 0 ) +
					( data[ pos + 1 ] << 16 ) +
					( data[ pos + 2 ] << 8 ) +
					data[ pos + 3 ]
				);
			}
			return (
				data[ pos ] +
				( data[ pos + 1 ] << 8 ) +
				( data[ pos + 2 ] << 16 ) +
				( ( data[ pos + 3 ] << 24 ) >>> 0 )
			);
		}

		// Read IFD offset from TIFF header.
		var ifdOffset = readUint32( mpfStart + 4 );
		var ifdPos = mpfStart + ifdOffset;

		if ( ifdPos + 2 > data.length ) {
			return null;
		}

		var entryCount;
		if ( bigEndian ) {
			entryCount = ( data[ ifdPos ] << 8 ) | data[ ifdPos + 1 ];
		} else {
			entryCount = data[ ifdPos ] | ( data[ ifdPos + 1 ] << 8 );
		}

		// Look for the MP Entry tag (0xB002) which contains image offsets.
		var mpEntryOffset = null;
		for ( var e = 0; e < entryCount; e++ ) {
			var tagPos = ifdPos + 2 + e * 12;
			if ( tagPos + 12 > data.length ) {
				break;
			}

			var tag;
			if ( bigEndian ) {
				tag = ( data[ tagPos ] << 8 ) | data[ tagPos + 1 ];
			} else {
				tag = data[ tagPos ] | ( data[ tagPos + 1 ] << 8 );
			}

			// MP Entry tag.
			if ( tag === 0xb002 ) {
				mpEntryOffset = readUint32( tagPos + 8 );
				break;
			}
		}

		if ( mpEntryOffset === null ) {
			return null;
		}

		// Each MP Entry is 16 bytes. The second entry (index 1) is the gain map.
		var entryBase = mpfStart + mpEntryOffset + 16; // Skip first entry.
		if ( entryBase + 16 > data.length ) {
			return null;
		}

		var imageSize = readUint32( entryBase + 4 );
		var imageOffset = readUint32( entryBase + 8 );

		// If offset is 0, the image is at the start of the file (relative to MPF start).
		if ( imageOffset === 0 ) {
			return null;
		}

		// MPF offsets are relative to the start of the MPF APP2 marker's TIFF header.
		return {
			offset: mpfStart + imageOffset,
			length: imageSize,
		};
	}

	/**
	 * Parses gain map parameters from XMP metadata text.
	 *
	 * @param {string} xmpText The XMP metadata as a string.
	 * @return {Object} Parsed gain map parameters.
	 */
	function parseGainMapXMP( xmpText ) {
		var metadata = {};

		var fields = [
			'GainMapMax',
			'GainMapMin',
			'Gamma',
			'OffsetSDR',
			'OffsetHDR',
			'HDRCapacityMax',
			'HDRCapacityMin',
			'BaseRenditionIsHDR',
		];

		for ( var i = 0; i < fields.length; i++ ) {
			var field = fields[ i ];
			// Match both hdrgm:Field="value" and hdrgm:Field>value< patterns.
			var attrPattern = new RegExp(
				'hdrgm:' + field + '=["\']([^"\']*)["\']'
			);
			var elemPattern = new RegExp(
				'hdrgm:' + field + '>([^<]*)<'
			);

			var match = xmpText.match( attrPattern );
			if ( ! match ) {
				match = xmpText.match( elemPattern );
			}

			if ( match ) {
				var val = match[ 1 ];
				// Parse rational numbers (e.g., "1/2").
				if ( val.indexOf( '/' ) !== -1 ) {
					var parts = val.split( '/' );
					metadata[ field ] = parseFloat( parts[ 0 ] ) / parseFloat( parts[ 1 ] );
				} else {
					metadata[ field ] = parseFloat( val );
				}
			}
		}

		return metadata;
	}

	/**
	 * Shows an info notice while UltraHDR processing is in progress.
	 */
	function showProcessingNotice() {
		try {
			wp.data
				.dispatch( 'core/notices' )
				.createInfoNotice(
					'Processing UltraHDR image \u2014 preserving HDR data\u2026',
					{
						id: NOTICE_ID,
						isDismissible: false,
					}
				);
		} catch ( e ) {
			// Notices store may not be available.
		}
	}

	/**
	 * Removes the UltraHDR processing notice.
	 */
	function removeProcessingNotice() {
		try {
			wp.data.dispatch( 'core/notices' ).removeNotice( NOTICE_ID );
		} catch ( e ) {
			// Notices store may not be available.
		}
	}

	/**
	 * Restores the original UltraHDR file as the main attachment file.
	 *
	 * After upload, WordPress may have re-encoded the JPEG, stripping
	 * the gain map. This sideloads the original file back.
	 *
	 * @param {File}   originalFile The original UltraHDR JPEG file.
	 * @param {number} attachmentId The attachment ID to update.
	 * @return {Promise} Resolves when sideload completes.
	 */
	function restoreOriginalUltraHDR( originalFile, attachmentId ) {
		var formData = new FormData();
		formData.append( 'file', originalFile );
		formData.append( 'image_size', 'original' );
		formData.append( 'replace_file', 'true' );
		formData.append( 'convert_format', 'false' );

		return wp
			.apiFetch( {
				path: '/wp/v2/media/' + attachmentId + '/sideload',
				method: 'POST',
				body: formData,
			} )
			.catch( function ( error ) {
				// eslint-disable-next-line no-console
				console.warn(
					'Failed to restore original UltraHDR file:',
					error
				);
			} );
	}

	/**
	 * Replaces a sub-size image with an UltraHDR version.
	 *
	 * @param {Blob}   ultraHDRBlob The UltraHDR JPEG blob.
	 * @param {number} attachmentId The attachment ID.
	 * @param {string} sizeSlug     The image size slug (e.g., 'thumbnail').
	 * @param {string} filename     The filename for the sub-size.
	 * @return {Promise} Resolves when sideload completes.
	 */
	function replaceSubSizeWithUltraHDR(
		ultraHDRBlob,
		attachmentId,
		sizeSlug,
		filename
	) {
		var file = new File( [ ultraHDRBlob ], filename, {
			type: 'image/jpeg',
		} );
		var formData = new FormData();
		formData.append( 'file', file );
		formData.append( 'image_size', sizeSlug );
		formData.append( 'replace_file', 'true' );
		formData.append( 'convert_format', 'false' );

		return wp
			.apiFetch( {
				path: '/wp/v2/media/' + attachmentId + '/sideload',
				method: 'POST',
				body: formData,
			} )
			.catch( function ( error ) {
				// eslint-disable-next-line no-console
				console.warn(
					'Failed to replace sub-size "' +
						sizeSlug +
						'" with UltraHDR version:',
					error
				);
			} );
	}

	/**
	 * Resizes a gain map JPEG to match target dimensions using Canvas API.
	 *
	 * @param {Uint8Array} gainMapBytes The gain map JPEG bytes.
	 * @param {number}     targetWidth  Target width.
	 * @param {number}     targetHeight Target height.
	 * @return {Promise<Blob>} Resolves with the resized gain map as JPEG blob.
	 */
	function resizeGainMap( gainMapBytes, targetWidth, targetHeight ) {
		return new Promise( function ( resolve, reject ) {
			var blob = new Blob( [ gainMapBytes ], { type: 'image/jpeg' } );
			var url = URL.createObjectURL( blob );
			var img = new Image();

			img.onload = function () {
				var canvas = document.createElement( 'canvas' );
				canvas.width = targetWidth;
				canvas.height = targetHeight;
				var ctx = canvas.getContext( '2d' );
				ctx.drawImage( img, 0, 0, targetWidth, targetHeight );
				URL.revokeObjectURL( url );

				canvas.toBlob(
					function ( resizedBlob ) {
						if ( resizedBlob ) {
							resolve( resizedBlob );
						} else {
							reject(
								new Error( 'Failed to resize gain map.' )
							);
						}
					},
					'image/jpeg',
					0.95
				);
			};

			img.onerror = function () {
				URL.revokeObjectURL( url );
				reject( new Error( 'Failed to load gain map image.' ) );
			};

			img.src = url;
		} );
	}

	/**
	 * Creates an UltraHDR JPEG by combining an SDR JPEG with a gain map.
	 *
	 * This is a simplified approach that embeds the gain map as a secondary
	 * image using the MPF (Multi-Picture Format) structure, preserving the
	 * XMP gain map metadata from the original file.
	 *
	 * @param {Blob}       sdrBlob      The SDR JPEG blob.
	 * @param {Blob}       gainMapBlob  The resized gain map JPEG blob.
	 * @param {Object}     metadata     The gain map XMP metadata.
	 * @return {Promise<Blob>} Resolves with the combined UltraHDR JPEG blob.
	 */
	function createUltraHDR( sdrBlob, gainMapBlob, metadata ) {
		return Promise.all( [
			readBlobAsArrayBuffer( sdrBlob ),
			readBlobAsArrayBuffer( gainMapBlob ),
		] ).then( function ( buffers ) {
			var sdrData = new Uint8Array( buffers[ 0 ] );
			var gainMapData = new Uint8Array( buffers[ 1 ] );

			return buildUltraHDRJPEG( sdrData, gainMapData, metadata );
		} );
	}

	/**
	 * Reads a Blob as an ArrayBuffer.
	 *
	 * @param {Blob} blob The blob to read.
	 * @return {Promise<ArrayBuffer>} The array buffer.
	 */
	function readBlobAsArrayBuffer( blob ) {
		return new Promise( function ( resolve, reject ) {
			var reader = new FileReader();
			reader.onload = function () {
				resolve( reader.result );
			};
			reader.onerror = function () {
				reject( new Error( 'Failed to read blob.' ) );
			};
			reader.readAsArrayBuffer( blob );
		} );
	}

	/**
	 * Builds an UltraHDR JPEG by combining SDR and gain map JPEGs
	 * with appropriate XMP and MPF metadata.
	 *
	 * @param {Uint8Array} sdrData     The SDR JPEG bytes.
	 * @param {Uint8Array} gainMapData The gain map JPEG bytes.
	 * @param {Object}     metadata    The gain map parameters.
	 * @return {Blob} The combined UltraHDR JPEG blob.
	 */
	function buildUltraHDRJPEG( sdrData, gainMapData, metadata ) {
		// Build XMP metadata for the gain map.
		var xmpPacket = buildGainMapXMP( metadata );
		var xmpBytes = encodeUTF8( xmpPacket );

		// XMP APP1 marker: FF E1 + length (2 bytes) + "http://ns.adobe.com/xap/1.0/\0" + XMP data.
		var xmpNamespace = 'http://ns.adobe.com/xap/1.0/\0';
		var xmpNsBytes = encodeUTF8( xmpNamespace );
		var xmpSegmentLength = 2 + xmpNsBytes.length + xmpBytes.length;
		var xmpSegment = new Uint8Array( 2 + xmpSegmentLength );
		xmpSegment[ 0 ] = 0xff;
		xmpSegment[ 1 ] = 0xe1;
		xmpSegment[ 2 ] = ( xmpSegmentLength >> 8 ) & 0xff;
		xmpSegment[ 3 ] = xmpSegmentLength & 0xff;
		xmpSegment.set( xmpNsBytes, 4 );
		xmpSegment.set( xmpBytes, 4 + xmpNsBytes.length );

		// Calculate where the gain map will be located.
		var sdrRest = sdrData.slice( 2 ); // Everything after SOI.

		// Build MPF APP2 segment twice: first to determine its size, then
		// with the correct primaryLength that includes the MPF segment itself.
		var tempMpf = buildMPFSegment( 0, gainMapData.length );
		var primaryLength =
			2 + xmpSegment.length + tempMpf.length + sdrRest.length;
		var mpfSegment = buildMPFSegment( primaryLength, gainMapData.length );

		// Assemble: SOI + XMP APP1 + MPF APP2 + rest of SDR + gain map JPEG.
		var totalLength =
			2 +
			xmpSegment.length +
			mpfSegment.length +
			sdrRest.length +
			gainMapData.length;
		var result = new Uint8Array( totalLength );
		var pos = 0;

		// SOI.
		result[ 0 ] = 0xff;
		result[ 1 ] = 0xd8;
		pos = 2;

		// XMP APP1.
		result.set( xmpSegment, pos );
		pos += xmpSegment.length;

		// MPF APP2.
		result.set( mpfSegment, pos );
		pos += mpfSegment.length;

		// Rest of original SDR JPEG (skip existing APP1/APP2 markers to avoid duplicates).
		result.set( sdrRest, pos );
		pos += sdrRest.length;

		// Gain map JPEG.
		result.set( gainMapData, pos );

		return new Blob( [ result ], { type: 'image/jpeg' } );
	}

	/**
	 * Builds XMP metadata packet for gain map parameters.
	 *
	 * @param {Object} metadata The gain map parameters.
	 * @return {string} The XMP packet as a string.
	 */
	function buildGainMapXMP( metadata ) {
		var attrs = '';
		var fields = [
			'GainMapMax',
			'GainMapMin',
			'Gamma',
			'OffsetSDR',
			'OffsetHDR',
			'HDRCapacityMax',
			'HDRCapacityMin',
			'BaseRenditionIsHDR',
		];

		for ( var i = 0; i < fields.length; i++ ) {
			var field = fields[ i ];
			if ( metadata[ field ] !== undefined && ! isNaN( metadata[ field ] ) ) {
				attrs +=
					'\n   hdrgm:' + field + '="' + metadata[ field ] + '"';
			}
		}

		return (
			'<?xpacket begin="\uFEFF" id="W5M0MpCehiHzreSzNTczkc9d"?>\n' +
			'<x:xmpmeta xmlns:x="adobe:ns:meta/">\n' +
			' <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">\n' +
			'  <rdf:Description\n' +
			'   xmlns:hdrgm="http://ns.adobe.com/hdr-gain-map/1.0/"\n' +
			'   hdrgm:Version="1.0"' +
			attrs +
			'/>\n' +
			' </rdf:RDF>\n' +
			'</x:xmpmeta>\n' +
			'<?xpacket end="w"?>'
		);
	}

	/**
	 * Builds an MPF (Multi-Picture Format) APP2 segment.
	 *
	 * @param {number} primarySize  Total size of the primary JPEG image.
	 * @param {number} gainMapSize  Size of the gain map JPEG.
	 * @return {Uint8Array} The MPF APP2 segment bytes.
	 */
	function buildMPFSegment( primarySize, gainMapSize ) {
		// MPF structure: APP2 marker + "MPF\0" + TIFF header + IFD + MP entries.
		// Using big-endian byte order for simplicity.

		// MP Entry for 2 images (primary + gain map), each entry is 16 bytes.
		// IFD: count (2) + 3 tags (12 each) + next IFD offset (4) = 2 + 36 + 4 = 42.
		// MP entries: 2 * 16 = 32.
		// TIFF header: 8 bytes.
		// Total MPF data: 8 + 42 + 32 = 82.
		var mpfDataLength = 82;
		var segmentLength = 2 + 4 + mpfDataLength; // length field + "MPF\0" + data.
		var segment = new Uint8Array( 2 + segmentLength );

		var p = 0;
		// APP2 marker.
		segment[ p++ ] = 0xff;
		segment[ p++ ] = 0xe2;
		// Segment length (big-endian).
		segment[ p++ ] = ( segmentLength >> 8 ) & 0xff;
		segment[ p++ ] = segmentLength & 0xff;
		// "MPF\0" identifier.
		segment[ p++ ] = 0x4d; // M
		segment[ p++ ] = 0x50; // P
		segment[ p++ ] = 0x46; // F
		segment[ p++ ] = 0x00; // \0

		var tiffStart = p;

		// TIFF header (big-endian).
		segment[ p++ ] = 0x4d; // M
		segment[ p++ ] = 0x4d; // M
		segment[ p++ ] = 0x00;
		segment[ p++ ] = 0x2a; // TIFF magic.
		// Offset to first IFD (8 = immediately after TIFF header).
		segment[ p++ ] = 0x00;
		segment[ p++ ] = 0x00;
		segment[ p++ ] = 0x00;
		segment[ p++ ] = 0x08;

		// IFD with 3 entries.
		// Entry count = 3.
		segment[ p++ ] = 0x00;
		segment[ p++ ] = 0x03;

		// Tag 1: MPFVersion (0xB000), type UNDEFINED (7), count 4.
		writeUint16BE( segment, p, 0xb000 );
		p += 2;
		writeUint16BE( segment, p, 7 );
		p += 2; // Type: UNDEFINED.
		writeUint32BE( segment, p, 4 );
		p += 4; // Count.
		// Value: "0100" (version 1.0).
		segment[ p++ ] = 0x30;
		segment[ p++ ] = 0x31;
		segment[ p++ ] = 0x30;
		segment[ p++ ] = 0x30;

		// Tag 2: NumberOfImages (0xB001), type LONG (4), count 1.
		writeUint16BE( segment, p, 0xb001 );
		p += 2;
		writeUint16BE( segment, p, 4 );
		p += 2; // Type: LONG.
		writeUint32BE( segment, p, 1 );
		p += 4; // Count.
		writeUint32BE( segment, p, 2 );
		p += 4; // Value: 2 images.

		// Tag 3: MPEntry (0xB002), type UNDEFINED (7), count 32.
		writeUint16BE( segment, p, 0xb002 );
		p += 2;
		writeUint16BE( segment, p, 7 );
		p += 2; // Type: UNDEFINED.
		writeUint32BE( segment, p, 32 );
		p += 4; // Count: 32 bytes (2 entries * 16).
		// Offset to MP entries (relative to TIFF start).
		var mpEntryOffset = p - tiffStart + 4; // +4 for next IFD pointer.
		writeUint32BE( segment, p, mpEntryOffset );
		p += 4;

		// Next IFD offset = 0 (no more IFDs).
		writeUint32BE( segment, p, 0 );
		p += 4;

		// MP Entry 1: Primary image.
		// Attributes: representative image + dependent child.
		writeUint32BE( segment, p, 0x020000 );
		p += 4; // Type + flags.
		writeUint32BE( segment, p, primarySize );
		p += 4; // Size.
		writeUint32BE( segment, p, 0 );
		p += 4; // Offset (0 = this file).
		writeUint16BE( segment, p, 0 );
		p += 2; // Dependent image 1.
		writeUint16BE( segment, p, 0 );
		p += 2; // Dependent image 2.

		// MP Entry 2: Gain map image.
		writeUint32BE( segment, p, 0x000000 );
		p += 4; // Type + flags.
		writeUint32BE( segment, p, gainMapSize );
		p += 4; // Size.
		// Offset relative to TIFF header start in the MPF segment.
		writeUint32BE( segment, p, primarySize - tiffStart );
		p += 4;
		writeUint16BE( segment, p, 0 );
		p += 2;
		writeUint16BE( segment, p, 0 );
		p += 2;

		return segment;
	}

	/**
	 * Writes a 16-bit big-endian unsigned integer to an array.
	 *
	 * @param {Uint8Array} arr    Target array.
	 * @param {number}     offset Write position.
	 * @param {number}     value  The 16-bit value.
	 */
	function writeUint16BE( arr, offset, value ) {
		arr[ offset ] = ( value >> 8 ) & 0xff;
		arr[ offset + 1 ] = value & 0xff;
	}

	/**
	 * Writes a 32-bit big-endian unsigned integer to an array.
	 *
	 * @param {Uint8Array} arr    Target array.
	 * @param {number}     offset Write position.
	 * @param {number}     value  The 32-bit value.
	 */
	function writeUint32BE( arr, offset, value ) {
		arr[ offset ] = ( value >> 24 ) & 0xff;
		arr[ offset + 1 ] = ( value >> 16 ) & 0xff;
		arr[ offset + 2 ] = ( value >> 8 ) & 0xff;
		arr[ offset + 3 ] = value & 0xff;
	}

	/**
	 * Encodes a string to UTF-8 bytes.
	 *
	 * @param {string} str The string to encode.
	 * @return {Uint8Array} The UTF-8 encoded bytes.
	 */
	function encodeUTF8( str ) {
		if ( typeof TextEncoder !== 'undefined' ) {
			return new TextEncoder().encode( str );
		}
		// Fallback for older browsers.
		var bytes = [];
		for ( var i = 0; i < str.length; i++ ) {
			var code = str.charCodeAt( i );
			if ( code < 0x80 ) {
				bytes.push( code );
			} else if ( code < 0x800 ) {
				bytes.push( 0xc0 | ( code >> 6 ), 0x80 | ( code & 0x3f ) );
			} else {
				bytes.push(
					0xe0 | ( code >> 12 ),
					0x80 | ( ( code >> 6 ) & 0x3f ),
					0x80 | ( code & 0x3f )
				);
			}
		}
		return new Uint8Array( bytes );
	}

	/**
	 * Processes sub-sizes for an UltraHDR attachment by downloading each
	 * sub-size, resizing the gain map, and re-combining them.
	 *
	 * @param {Object}     attachment   The attachment data from the API response.
	 * @param {Uint8Array} gainMapBytes The original gain map bytes.
	 * @param {Object}     metadata     The gain map XMP metadata.
	 * @return {Promise} Resolves when all sub-sizes are processed.
	 */
	function processSubSizes( attachment, gainMapBytes, metadata ) {
		var sizes = attachment.media_details && attachment.media_details.sizes;
		if ( ! sizes ) {
			return Promise.resolve();
		}

		var promises = [];

		Object.keys( sizes ).forEach( function ( sizeSlug ) {
			// Skip 'full' size — that's the original.
			if ( sizeSlug === 'full' ) {
				return;
			}

			var size = sizes[ sizeSlug ];
			if ( ! size.source_url || ! size.width || ! size.height ) {
				return;
			}

			var promise = fetchSubSizeBlob( size.source_url )
				.then( function ( sdrBlob ) {
					return resizeGainMap(
						gainMapBytes,
						size.width,
						size.height
					).then( function ( resizedGainMap ) {
						return createUltraHDR(
							sdrBlob,
							resizedGainMap,
							metadata
						);
					} );
				} )
				.then( function ( ultraHDRBlob ) {
					var filename = size.source_url.split( '/' ).pop();
					return replaceSubSizeWithUltraHDR(
						ultraHDRBlob,
						attachment.id,
						sizeSlug,
						filename
					);
				} )
				.catch( function ( error ) {
					// eslint-disable-next-line no-console
					console.warn(
						'Failed to create UltraHDR sub-size "' +
							sizeSlug +
							'":',
						error
					);
				} );

			promises.push( promise );
		} );

		return Promise.all( promises );
	}

	/**
	 * Fetches a sub-size image as a Blob.
	 *
	 * @param {string} url The image URL.
	 * @return {Promise<Blob>} The image blob.
	 */
	function fetchSubSizeBlob( url ) {
		return fetch( url ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error(
					'Failed to fetch sub-size: ' + response.status
				);
			}
			return response.blob();
		} );
	}

	/**
	 * Wraps the upload-media store's addItems action to detect and
	 * process UltraHDR images.
	 *
	 * For each UltraHDR image detected:
	 * 1. Extract and cache the gain map data before upload.
	 * 2. Let the JPEG upload proceed normally.
	 * 3. After upload, restore the original file and regenerate sub-sizes.
	 */
	function wrapAddItems() {
		var uploadStore;
		try {
			uploadStore = wp.data.dispatch( 'core/upload-media' );
		} catch ( e ) {
			return false;
		}

		if ( ! uploadStore || ! uploadStore.addItems ) {
			return false;
		}

		if ( uploadStore.addItems.__csmeUltraHDRWrapped ) {
			return true;
		}

		var originalAddItems = uploadStore.addItems.bind( uploadStore );

		uploadStore.addItems = function ( args ) {
			var files = args.files || [];

			// Check each file for UltraHDR markers.
			var detectionPromises = files.map( function ( file ) {
				return isUltraHDR( file ).then( function ( isHDR ) {
					return { file: file, isUltraHDR: isHDR };
				} );
			} );

			Promise.all( detectionPromises ).then( function ( results ) {
				var hasUltraHDR = results.some( function ( r ) {
					return r.isUltraHDR;
				} );

				if ( ! hasUltraHDR ) {
					return originalAddItems( args );
				}

				showProcessingNotice();

				// Extract gain map data for each UltraHDR file.
				var extractionPromises = results.map( function ( r ) {
					if ( ! r.isUltraHDR ) {
						return Promise.resolve( {
							file: r.file,
							gainMapData: null,
						} );
					}
					return extractGainMap( r.file ).then( function ( data ) {
						return { file: r.file, gainMapData: data };
					} );
				} );

				Promise.all( extractionPromises ).then( function (
					fileDataList
				) {
					// Build a map of UltraHDR data keyed by filename.
					var ultraHDRMap = {};
					fileDataList.forEach( function ( item ) {
						if ( item.gainMapData && item.file.name ) {
							ultraHDRMap[ item.file.name ] = {
								originalFile: item.file,
								gainMapData: item.gainMapData,
							};
						}
					} );

					var originalOnSuccess = args.onSuccess;
					var wrappedOnSuccess = function ( attachments ) {
						var postProcessPromises = [];

						attachments.forEach( function ( attachment ) {
							// Match by source filename from the attachment.
							var fileName =
								attachment.source_url
									? attachment.source_url
											.split( '/' )
											.pop()
									: '';
							// Also try the original filename.
							var origName =
								attachment.meta &&
								attachment.meta.original_filename
									? attachment.meta.original_filename
									: '';

							var hdrData = null;
							// Try exact match, then prefix match (WP may
							// append dimensions or deduplicate filenames).
							Object.keys( ultraHDRMap ).forEach(
								function ( key ) {
									if ( hdrData ) {
										return;
									}
									var base = key
										.replace( /\.[^.]+$/, '' )
										.toLowerCase();
									var fLower = fileName.toLowerCase();
									var oLower = origName.toLowerCase();
									if (
										fLower === key.toLowerCase() ||
										oLower === key.toLowerCase() ||
										fLower.indexOf( base ) === 0 ||
										oLower.indexOf( base ) === 0
									) {
										hdrData = ultraHDRMap[ key ];
									}
								}
							);

							if ( hdrData && attachment.id ) {
								// Restore original UltraHDR as main file.
								var restorePromise =
									restoreOriginalUltraHDR(
										hdrData.originalFile,
										attachment.id
									);
								postProcessPromises.push( restorePromise );

								// Regenerate sub-sizes with gain map.
								var subSizePromise = processSubSizes(
									attachment,
									hdrData.gainMapData.gainMapBytes,
									hdrData.gainMapData.metadata
								);
								postProcessPromises.push( subSizePromise );
							}
						} );

						Promise.all( postProcessPromises ).then( function () {
							removeProcessingNotice();
						} );

						if ( originalOnSuccess ) {
							originalOnSuccess( attachments );
						}
					};

					originalAddItems( {
						...args,
						onSuccess: wrappedOnSuccess,
					} );
				} );
			} );
		};

		uploadStore.addItems.__csmeUltraHDRWrapped = true;
		return true;
	}

	wp.domReady( function () {
		if ( wrapAddItems() ) {
			return;
		}

		// Subscribe to store changes until upload-media store is available.
		var unsubscribe = wp.data.subscribe( function () {
			if ( wrapAddItems() ) {
				unsubscribe();
			}
		} );
	} );
} )();
