/**
 * WP Folder Boss — Media Library Grid Integration
 *
 * Integrates with wp.media to:
 *  1. Add the folder sidebar to the media grid (AttachmentsBrowser).
 *  2. Filter attachments by selected folder.
 *  3. Auto-assign newly uploaded files to the active folder.
 */
/* global wpfbData, wpfbMediaData, wpfbTree */

( function ( $ ) {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.media ) {
		return;
	}

	// Active folder ID (-1 = all items).
	let activeFolderId = -1;

	// --------------------------------------------------------------------- //
	//  Extend AttachmentsBrowser to inject sidebar and filter by folder      //
	// --------------------------------------------------------------------- //

	const OriginalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;

	wp.media.view.AttachmentsBrowser = OriginalAttachmentsBrowser.extend( {

		/**
		 * Override createSidebar to also inject our folder sidebar.
		 */
		createSidebar: function () {
			OriginalAttachmentsBrowser.prototype.createSidebar.apply( this, arguments );
			this._injectFolderSidebar();
		},

		/**
		 * Inject the WP Folder Boss sidebar HTML into the media frame.
		 */
		_injectFolderSidebar: function () {
			const sidebar = document.getElementById( 'wpfb-sidebar' );
			if ( ! sidebar ) return;

			const browserEl = this.el;
			if ( ! browserEl ) return;

			// Move the sidebar into the browser element.
			browserEl.insertBefore( sidebar, browserEl.firstChild );
			sidebar.style.display = '';

			// When a folder is selected, filter the collection.
			document.addEventListener( 'wpfb:folder-selected', ( e ) => {
				if ( e.detail.context !== 'media' ) return;
				activeFolderId = parseInt( e.detail.folderId, 10 );
				this._filterByFolder( activeFolderId );
			} );
		},

		/**
		 * Trigger a re-query of the attachment collection with the folder filter.
		 *
		 * @param {number} folderId
		 */
		_filterByFolder: function ( folderId ) {
			const collection = this.collection;
			if ( ! collection ) return;

			const props = collection.props;

			if ( folderId === -1 ) {
				// All items — remove any folder filter.
				props.unset( 'wpfb_folder' );
			} else {
				props.set( 'wpfb_folder', folderId );
			}

			// Refresh the collection.
			collection.reset();
			collection.more();
		},

	} );

	// --------------------------------------------------------------------- //
	//  Inject folder filter param into AJAX request for attachments          //
	// --------------------------------------------------------------------- //

	/**
	 * Extend wp.media.model.Query to pass the wpfb_folder param.
	 */
	const OriginalQuery = wp.media.model.Query;

	// Override the `sync` method to inject the folder query arg.
	const originalSync = OriginalQuery.prototype.sync;

	OriginalQuery.prototype.sync = function ( method, model, options ) {
		if ( 'read' === method ) {
			const folderId = this.props.get( 'wpfb_folder' );
			if ( typeof folderId !== 'undefined' ) {
				const origData   = options.data || {};
				options.data     = Object.assign( {}, origData, { wpfb_folder: folderId } );
			}
		}

		return originalSync.call( this, method, model, options );
	};

	// --------------------------------------------------------------------- //
	//  Handle server-side folder filtering                                   //
	// --------------------------------------------------------------------- //

	// Add a custom query variable handler so WP will recognise wpfb_folder in
	// the attachments AJAX endpoint (wp_ajax_query-attachments).
	//
	// On the PHP side, the MediaLibrary class already hooks into pre_get_posts,
	// but for the media modal we need to handle via wp_query_vars approach.
	// The media modal sends extra params in `query` — we pass it as post_query.
	// NOTE: The PHP side reads $_REQUEST['query']['wpfb_folder'] via
	//       WP_Query's `tax_query`. This JS layer simply ensures the param
	//       is appended to the query data.

	$( document ).on( 'heartbeat-send.wpfb', function () {
		// Not needed but here for extensibility.
	} );

	// --------------------------------------------------------------------- //
	//  Auto-assign uploads to the active folder                              //
	// --------------------------------------------------------------------- //

	if ( wp.Uploader ) {
		const originalInit = wp.Uploader.prototype.init;

		wp.Uploader.prototype.init = function () {
			originalInit && originalInit.apply( this, arguments );

			this.uploader.bind( 'FileUploaded', function ( uploader, file, response ) {
				const attachmentId = response && response.response && response.response.id;
				if ( ! attachmentId ) return;
				if ( activeFolderId <= 0 ) return;

				// Assign the uploaded file to the active folder via REST.
				fetch( wpfbData.restUrl + '/assign', {
					method  : 'POST',
					headers : {
						'Content-Type': 'application/json',
						'X-WP-Nonce'  : wpfbData.nonce,
					},
					body: JSON.stringify( {
						folder_id : activeFolderId,
						item_ids  : [ attachmentId ],
						item_type : 'post',
					} ),
				} ).catch( () => {} );
			} );
		};
	}

	// --------------------------------------------------------------------- //
	//  React to items being moved (update folder counts in sidebar)          //
	// --------------------------------------------------------------------- //

	document.addEventListener( 'wpfb:items-moved', () => {
		// Refresh media grid.
		if ( wp.media && wp.media.frame ) {
			try {
				wp.media.frame.content.get().collection.reset();
				wp.media.frame.content.get().collection.more();
			} catch ( e ) {
				// Frame may not be open.
			}
		}
	} );

} )( jQuery );
