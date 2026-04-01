/**
 * WP Folder Boss — Drag & Drop Logic (HTML5 native DnD API)
 *
 * Handles:
 *  1. Dragging folder tree nodes to reorder / re-nest them.
 *  2. Dragging content items (media thumbnails, table rows) onto folder nodes.
 */
/* global wpfbData */

( function () {
	'use strict';

	const CSS_DROP_TARGET = 'wpfb-drop-target';
	const CSS_DRAGGING    = 'wpfb-dragging';

	let dragSrcItem  = null; // The folder <li> being dragged (for folder reorder).
	let dragItemIds  = [];   // Content item IDs being dragged onto a folder.
	let dragItemType = 'post';

	/**
	 * REST API call helper.
	 *
	 * @param {string} method
	 * @param {string} path
	 * @param {object} body
	 * @returns {Promise}
	 */
	function api( method, path, body ) {
		return fetch( wpfbData.restUrl + path, {
			method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpfbData.nonce,
			},
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( ( r ) => r.json() );
	}

	// ------------------------------------------------------------------ //
	//  FOLDER TREE — Drag folder nodes to reorder / re-nest               //
	// ------------------------------------------------------------------ //

	/**
	 * Make all folder items in the tree draggable and handle folder drag events.
	 *
	 * @param {HTMLElement} tree
	 */
	function bindFolderDragEvents( tree ) {
		tree.addEventListener( 'dragstart', ( e ) => {
			const item = e.target.closest( '.wpfb-folder-item:not(.wpfb-virtual)' );
			if ( ! item ) return;

			dragSrcItem = item;
			item.classList.add( CSS_DRAGGING );
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'text/plain', item.dataset.id );
		} );

		tree.addEventListener( 'dragend', () => {
			if ( dragSrcItem ) {
				dragSrcItem.classList.remove( CSS_DRAGGING );
				dragSrcItem = null;
			}
			clearDropTargets( tree );
		} );

		tree.addEventListener( 'dragover', ( e ) => {
			e.preventDefault();
			const target = e.target.closest( '.wpfb-folder-item' );
			if ( ! target || target === dragSrcItem ) return;

			clearDropTargets( tree );
			target.classList.add( CSS_DROP_TARGET );
			e.dataTransfer.dropEffect = 'move';
		} );

		tree.addEventListener( 'dragleave', ( e ) => {
			const target = e.target.closest( '.wpfb-folder-item' );
			if ( target ) {
				target.classList.remove( CSS_DROP_TARGET );
			}
		} );

		tree.addEventListener( 'drop', ( e ) => {
			e.preventDefault();
			clearDropTargets( tree );

			const targetItem = e.target.closest( '.wpfb-folder-item' );
			if ( ! targetItem || ! dragSrcItem || targetItem === dragSrcItem ) return;

			const srcId    = parseInt( dragSrcItem.dataset.id, 10 );
			const targetId = parseInt( targetItem.dataset.id, 10 );

			// Determine intent: drop ON a folder = nest inside; drop BETWEEN = reorder.
			// For simplicity: dropping on a folder nests inside it;
			// virtual items mean "move to root".
			const newParent = targetItem.classList.contains( 'wpfb-virtual' ) ? 0 : targetId;

			api( 'PUT', `/folders/${ srcId }`, { parent: newParent } )
				.then( () => {
					// Move the DOM element.
					let children;
					if ( newParent === 0 ) {
						tree.appendChild( dragSrcItem );
					} else {
						children = targetItem.querySelector( '.wpfb-folder-children' );
						if ( ! children ) {
							children = document.createElement( 'ul' );
							children.className = 'wpfb-folder-children';
							children.setAttribute( 'role', 'group' );
							targetItem.appendChild( children );
							targetItem.classList.add( 'wpfb-has-children' );
						}
						children.style.display = '';
						targetItem.setAttribute( 'aria-expanded', 'true' );
						children.appendChild( dragSrcItem );
					}
					dragSrcItem.dataset.parent = String( newParent );
				} )
				.catch( () => {} );
		} );
	}

	// ------------------------------------------------------------------ //
	//  CONTENT ITEMS — Drag media thumbnails / table rows onto folders    //
	// ------------------------------------------------------------------ //

	/**
	 * Make a single content item draggable (call once per item element).
	 *
	 * @param {HTMLElement} el      The draggable element.
	 * @param {number}      itemId  The post/user/plugin ID.
	 * @param {string}      type    Item type: 'post', 'user', 'plugin'.
	 */
	function makeItemDraggable( el, itemId, type ) {
		el.setAttribute( 'draggable', 'true' );

		el.addEventListener( 'dragstart', ( e ) => {
			// If multiple items are selected (bulk), drag them all.
			const selectedIds = getSelectedItemIds( type );
			if ( selectedIds.length > 0 && selectedIds.includes( itemId ) ) {
				dragItemIds  = selectedIds;
			} else {
				dragItemIds  = [ itemId ];
			}
			dragItemType = type;
			el.classList.add( CSS_DRAGGING );
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'application/wpfb-item', JSON.stringify( { ids: dragItemIds, type } ) );
		} );

		el.addEventListener( 'dragend', () => {
			el.classList.remove( CSS_DRAGGING );
		} );
	}

	/**
	 * Bind drop events on the folder tree to accept content item drops.
	 *
	 * @param {HTMLElement} tree
	 */
	function bindFolderDropzones( tree ) {
		tree.addEventListener( 'dragover', ( e ) => {
			// Only handle content item drags (not folder drags).
			if ( dragSrcItem ) return;
			if ( ! dragItemIds.length ) return;

			e.preventDefault();
			const target = e.target.closest( '.wpfb-folder-item' );
			if ( ! target ) return;

			clearDropTargets( tree );
			target.classList.add( CSS_DROP_TARGET );
			e.dataTransfer.dropEffect = 'move';
		} );

		tree.addEventListener( 'drop', ( e ) => {
			if ( dragSrcItem ) return;
			if ( ! dragItemIds.length ) return;

			e.preventDefault();
			clearDropTargets( tree );

			const targetItem = e.target.closest( '.wpfb-folder-item' );
			if ( ! targetItem ) return;

			const folderId = parseInt( targetItem.dataset.id, 10 );
			const ids      = dragItemIds.slice();
			const type     = dragItemType;

			dragItemIds = [];

			api( 'POST', '/assign', {
				folder_id : folderId > 0 ? folderId : 0,
				item_ids  : ids,
				item_type : type,
			} ).then( () => {
				// Notify the tree to refresh counts.
				document.dispatchEvent(
					new CustomEvent( 'wpfb:items-moved', { detail: { folderId, ids, type } } )
				);
			} ).catch( () => {} );
		} );
	}

	/**
	 * Get currently selected item IDs for the given type.
	 *
	 * @param {string} type
	 * @returns {number[]}
	 */
	function getSelectedItemIds( type ) {
		const ids = [];

		if ( 'post' === type ) {
			// Media grid: selected attachments.
			if ( window.wp && wp.media && wp.media.frame ) {
				const selected = wp.media.frame.state().get( 'selection' );
				if ( selected ) {
					selected.each( ( model ) => ids.push( model.id ) );
					return ids;
				}
			}

			// List table: checked checkboxes.
			document.querySelectorAll( '.wp-list-table input[type="checkbox"]:checked' ).forEach( ( cb ) => {
				const val = parseInt( cb.value, 10 );
				if ( val ) ids.push( val );
			} );
		} else if ( 'user' === type ) {
			document.querySelectorAll( '#the-list input[type="checkbox"]:checked' ).forEach( ( cb ) => {
				const val = parseInt( cb.value, 10 );
				if ( val ) ids.push( val );
			} );
		}

		return ids;
	}

	/**
	 * Remove CSS_DROP_TARGET class from all items.
	 *
	 * @param {HTMLElement} tree
	 */
	function clearDropTargets( tree ) {
		tree.querySelectorAll( '.' + CSS_DROP_TARGET ).forEach( ( el ) => {
			el.classList.remove( CSS_DROP_TARGET );
		} );
	}

	// ------------------------------------------------------------------ //
	//  Media grid — make thumbnails draggable                             //
	// ------------------------------------------------------------------ //

	/**
	 * Observe the media grid for new attachment items and make them draggable.
	 */
	function observeMediaGrid() {
		const grid = document.querySelector( '.attachments-browser .attachments' );
		if ( ! grid ) return;

		const handleItem = ( el ) => {
			if ( el.classList.contains( 'attachment' ) && ! el.dataset.wpfbDraggable ) {
				el.dataset.wpfbDraggable = '1';
				const id = parseInt( el.dataset.id, 10 );
				if ( id ) {
					makeItemDraggable( el, id, 'post' );
				}
			}
		};

		// Existing items.
		grid.querySelectorAll( '.attachment' ).forEach( handleItem );

		// New items added dynamically.
		const observer = new MutationObserver( ( mutations ) => {
			mutations.forEach( ( m ) => {
				m.addedNodes.forEach( ( node ) => {
					if ( node.nodeType === 1 ) {
						handleItem( node );
						node.querySelectorAll && node.querySelectorAll( '.attachment' ).forEach( handleItem );
					}
				} );
			} );
		} );

		observer.observe( grid, { childList: true, subtree: true } );
	}

	/**
	 * Make media list-table rows draggable.
	 */
	function bindMediaListRows() {
		document.querySelectorAll( '#the-list tr' ).forEach( ( row ) => {
			const cb = row.querySelector( 'input[type="checkbox"]' );
			if ( ! cb ) return;
			const id = parseInt( cb.value, 10 );
			if ( id ) {
				makeItemDraggable( row, id, 'post' );
			}
		} );
	}

	/**
	 * Make post list-table rows draggable (edit.php).
	 */
	function bindPostListRows() {
		document.querySelectorAll( '.wp-list-table#the-list tr' ).forEach( ( row ) => {
			const cb = row.querySelector( 'input[type="checkbox"]' );
			if ( ! cb ) return;
			const id = parseInt( cb.value, 10 );
			if ( id ) {
				makeItemDraggable( row, id, 'post' );
			}
		} );
	}

	/**
	 * Make user list rows draggable.
	 */
	function bindUserListRows() {
		document.querySelectorAll( '#users-list tr' ).forEach( ( row ) => {
			const cb = row.querySelector( 'input[type="checkbox"]' );
			if ( ! cb ) return;
			const id = parseInt( cb.value, 10 );
			if ( id ) {
				makeItemDraggable( row, id, 'user' );
			}
		} );
	}

	/**
	 * Initialize drag-and-drop.
	 */
	function init() {
		const tree = document.getElementById( 'wpfb-folder-tree' );
		if ( ! tree ) return;

		bindFolderDragEvents( tree );
		bindFolderDropzones( tree );

		// Bind content item rows depending on current screen.
		bindMediaListRows();
		bindPostListRows();
		bindUserListRows();
		observeMediaGrid();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Expose for use by other modules.
	window.wpfbDragDrop = { makeItemDraggable };
} )();
