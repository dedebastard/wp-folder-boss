/**
 * WP Folder Boss — Folder Tree Sidebar Component
 */
/* global wpfbData */

( function () {
	'use strict';

	var STORAGE_KEY = 'wpfb_expanded';

	function getExpanded() {
		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			return new Set( raw ? JSON.parse( raw ) : [] );
		} catch ( e ) {
			return new Set();
		}
	}

	function saveExpanded( expanded ) {
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( Array.from( expanded ) ) );
		} catch ( e ) {}
	}

	function api( method, path, body ) {
		var opts = {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpfbData.nonce,
			},
		};
		if ( body ) {
			opts.body = JSON.stringify( body );
		}
		return fetch( wpfbData.restUrl + path, opts ).then( function ( r ) {
			if ( ! r.ok ) {
				return r.json().then( function ( err ) { return Promise.reject( err ); } );
			}
			return r.json();
		} );
	}

	/**
	 * WPFBTree — the main folder tree controller.
	 */
	function WPFBTree( sidebar ) {
		this.sidebar     = sidebar;
		this.tree        = sidebar.querySelector( '#wpfb-folder-tree' );
		this.context     = sidebar.dataset.context || 'media';
		this.contextMenu = document.getElementById( 'wpfb-context-menu' );
		this.expanded    = getExpanded();
		this.activeId    = '-1';
		this.ctxTarget   = null;

		this._bindEvents();
		this._restoreExpanded();

		// Check URL for saved folder selection (list view)
		var urlParams = new URLSearchParams( window.location.search );
		var savedFolder = urlParams.get( 'wpfb_folder' );
		if ( savedFolder !== null ) {
			var savedItem = this.tree.querySelector( '[data-id="' + CSS.escape( savedFolder ) + '"]' );
			if ( savedItem ) {
				this._selectNode( savedItem, true ); // true = skip reload
			} else {
				this._selectNode( this.tree.querySelector( '[data-id="-1"]' ), true );
			}
		} else {
			this._selectNode( this.tree.querySelector( '[data-id="-1"]' ), true );
		}
	}

	WPFBTree.prototype._bindEvents = function () {
		var self = this;

		this.tree.addEventListener( 'click', function ( e ) {
			var node   = e.target.closest( '.wpfb-folder-node' );
			var toggle = e.target.closest( '.wpfb-toggle-btn' );
			var item   = e.target.closest( '.wpfb-folder-item' );

			if ( ! item ) return;

			if ( toggle ) {
				e.stopPropagation();
				self._toggleItem( item );
				return;
			}

			if ( node ) {
				self._selectNode( item, false );
			}
		} );

		this.tree.addEventListener( 'dblclick', function ( e ) {
			var node = e.target.closest( '.wpfb-folder-node' );
			if ( ! node ) return;
			var item = node.closest( '.wpfb-folder-item' );
			if ( ! item || item.classList.contains( 'wpfb-virtual' ) ) return;
			self._startRename( item );
		} );

		this.tree.addEventListener( 'contextmenu', function ( e ) {
			e.preventDefault();
			var item = e.target.closest( '.wpfb-folder-item' );
			self.ctxTarget = item || null;
			self._showContextMenu( e.clientX, e.clientY, item );
		} );

		var addBtn = this.sidebar.querySelector( '.wpfb-add-folder-btn' );
		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () { self._promptNewFolder( 0 ); } );
		}

		if ( this.contextMenu ) {
			this.contextMenu.addEventListener( 'click', function ( e ) {
				var li = e.target.closest( '[data-action]' );
				if ( ! li ) return;
				self._handleContextAction( li.dataset.action );
				self._hideContextMenu();
			} );
		}

		document.addEventListener( 'click', function ( e ) {
			if ( self.contextMenu && ! self.contextMenu.contains( e.target ) ) {
				self._hideContextMenu();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				self._hideContextMenu();
			}
		} );
	};

	WPFBTree.prototype._toggleItem = function ( item ) {
		var children  = item.querySelector( '.wpfb-folder-children' );
		var toggleBtn = item.querySelector( '.wpfb-toggle-btn' );
		var id        = item.dataset.id;
		var isExpanded = item.getAttribute( 'aria-expanded' ) === 'true';

		if ( ! children ) return;

		if ( isExpanded ) {
			children.style.display = 'none';
			item.setAttribute( 'aria-expanded', 'false' );
			if ( toggleBtn ) toggleBtn.innerHTML = '&#9658;';
			this.expanded.delete( id );
		} else {
			children.style.display = '';
			item.setAttribute( 'aria-expanded', 'true' );
			if ( toggleBtn ) toggleBtn.innerHTML = '&#9660;';
			this.expanded.add( id );
		}

		saveExpanded( this.expanded );
	};

	WPFBTree.prototype._restoreExpanded = function () {
		var self = this;
		this.expanded.forEach( function ( id ) {
			var item = self.tree.querySelector( '[data-id="' + CSS.escape( id ) + '"]' );
			if ( item ) {
				var children = item.querySelector( '.wpfb-folder-children' );
				var toggleBtn = item.querySelector( '.wpfb-toggle-btn' );
				if ( children ) {
					children.style.display = '';
					item.setAttribute( 'aria-expanded', 'true' );
					if ( toggleBtn ) toggleBtn.innerHTML = '&#9660;';
				}
			}
		} );
	};

	/**
	 * @param {HTMLElement|null} item
	 * @param {boolean} skipReload - if true, don't reload page (used on init)
	 */
	WPFBTree.prototype._selectNode = function ( item, skipReload ) {
		if ( ! item ) return;

		this.tree.querySelectorAll( '[aria-selected="true"]' ).forEach( function ( el ) {
			el.setAttribute( 'aria-selected', 'false' );
		} );

		item.setAttribute( 'aria-selected', 'true' );
		this.activeId = item.dataset.id;

		// Fire custom event for media-library.js (grid view)
		document.dispatchEvent(
			new CustomEvent( 'wpfb:folder-selected', {
				detail: {
					folderId: this.activeId,
					context: this.context,
				},
			} )
		);

		// For LIST VIEW: reload the page with ?wpfb_folder=ID
		if ( ! skipReload && this.context === 'media' ) {
			var isListMode = document.querySelector( '.wp-list-table.media' ) !== null
				|| window.location.search.indexOf( 'mode=list' ) !== -1;

			if ( isListMode ) {
				var url = new URL( window.location.href );
				if ( this.activeId === '-1' ) {
					url.searchParams.delete( 'wpfb_folder' );
				} else {
					url.searchParams.set( 'wpfb_folder', this.activeId );
				}
				url.searchParams.delete( 'paged' );
				window.location.href = url.toString();
				return;
			}
		}
	};

	WPFBTree.prototype._showContextMenu = function ( x, y, item ) {
		if ( ! this.contextMenu ) return;

		var isVirtual  = item && item.classList.contains( 'wpfb-virtual' );
		var renameItem = this.contextMenu.querySelector( '[data-action="rename"]' );
		var deleteItem = this.contextMenu.querySelector( '[data-action="delete"]' );
		var subItem    = this.contextMenu.querySelector( '[data-action="new-subfolder"]' );

		if ( renameItem ) renameItem.style.display = isVirtual || ! item ? 'none' : '';
		if ( deleteItem ) deleteItem.style.display = isVirtual || ! item ? 'none' : '';
		if ( subItem ) subItem.style.display = ! item ? 'none' : '';

		this.contextMenu.style.display = '';
		this.contextMenu.style.left    = x + 'px';
		this.contextMenu.style.top     = y + 'px';
	};

	WPFBTree.prototype._hideContextMenu = function () {
		if ( this.contextMenu ) {
			this.contextMenu.style.display = 'none';
		}
	};

	WPFBTree.prototype._handleContextAction = function ( action ) {
		var item = this.ctxTarget;
		var id   = item ? item.dataset.id : null;

		switch ( action ) {
			case 'new-folder':
				this._promptNewFolder( 0 );
				break;
			case 'new-subfolder':
				if ( id && id !== '-1' ) {
					this._promptNewFolder( parseInt( id, 10 ) );
				}
				break;
			case 'rename':
				if ( item && ! item.classList.contains( 'wpfb-virtual' ) ) {
					this._startRename( item );
				}
				break;
			case 'delete':
				if ( item && ! item.classList.contains( 'wpfb-virtual' ) ) {
					this._deleteFolder( item );
				}
				break;
		}
	};

	WPFBTree.prototype._promptNewFolder = function ( parentId ) {
		var name = prompt( wpfbData.i18n.newFolder );
		if ( ! name || ! name.trim() ) return;
		var self = this;

		api( 'POST', '/folders', {
			name: name.trim(),
			context_key: this.context,
			parent: parentId,
			order: 0,
		} ).then( function ( folder ) {
			self._addFolderToTree( folder, parentId );
		} ).catch( function () {} );
	};

	WPFBTree.prototype._addFolderToTree = function ( folder, parentId ) {
		var li = this._buildFolderLI( folder );

		if ( parentId === 0 ) {
			this.tree.appendChild( li );
		} else {
			var parentItem = this.tree.querySelector( '[data-id="' + parentId + '"]' );
			if ( ! parentItem ) {
				this.tree.appendChild( li );
				return;
			}

			var children = parentItem.querySelector( '.wpfb-folder-children' );
			if ( ! children ) {
				children = document.createElement( 'ul' );
				children.className = 'wpfb-folder-children';
				children.setAttribute( 'role', 'group' );
				parentItem.appendChild( children );
				parentItem.classList.add( 'wpfb-has-children' );

				var node = parentItem.querySelector( '.wpfb-folder-node' );
				var placeholder = parentItem.querySelector( '.wpfb-toggle-placeholder' );
				if ( node && placeholder ) {
					var btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'wpfb-toggle-btn';
					btn.setAttribute( 'aria-label', wpfbData.i18n.newFolder );
					btn.innerHTML = '&#9658;';
					placeholder.replaceWith( btn );
				}
			}

			children.style.display = '';
			parentItem.setAttribute( 'aria-expanded', 'true' );
			children.appendChild( li );
		}
	};

	WPFBTree.prototype._buildFolderLI = function ( folder ) {
		var li = document.createElement( 'li' );
		li.className = 'wpfb-folder-item';
		li.dataset.id     = folder.id;
		li.dataset.parent = folder.parent;
		li.dataset.order  = folder.order;
		li.setAttribute( 'role', 'treeitem' );
		li.setAttribute( 'aria-expanded', 'false' );
		li.setAttribute( 'aria-selected', 'false' );
		li.setAttribute( 'draggable', 'true' );

		li.innerHTML =
			'<span class="wpfb-folder-node">' +
				'<span class="wpfb-toggle-placeholder"></span>' +
				'<img src="' + wpfbData.folderIcon + '" class="wpfb-folder-icon" alt="" />' +
				'<span class="wpfb-folder-name">' + this._esc( folder.name ) + '</span>' +
				'<span class="wpfb-folder-count">' + ( folder.count || 0 ) + '</span>' +
			'</span>';

		return li;
	};

	WPFBTree.prototype._esc = function ( str ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( str ) );
		return d.innerHTML;
	};

	WPFBTree.prototype._startRename = function ( item ) {
		var nameSpan = item.querySelector( '.wpfb-folder-name' );
		if ( ! nameSpan ) return;

		var currentName = nameSpan.textContent;
		var input       = document.createElement( 'input' );
		input.type      = 'text';
		input.className = 'wpfb-rename-input';
		input.value     = currentName;

		nameSpan.replaceWith( input );
		input.focus();
		input.select();

		var finished = false;
		var finish = function () {
			if ( finished ) return;
			finished = true;
			var newName = input.value.trim();
			if ( newName && newName !== currentName ) {
				api( 'PUT', '/folders/' + item.dataset.id, { name: newName } )
					.then( function ( folder ) {
						var span = document.createElement( 'span' );
						span.className = 'wpfb-folder-name';
						span.textContent = folder.name;
						input.replaceWith( span );
					} )
					.catch( function () {
						var span = document.createElement( 'span' );
						span.className = 'wpfb-folder-name';
						span.textContent = currentName;
						input.replaceWith( span );
					} );
			} else {
				var span = document.createElement( 'span' );
				span.className = 'wpfb-folder-name';
				span.textContent = currentName;
				input.replaceWith( span );
			}
		};

		input.addEventListener( 'blur', finish );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				finish();
			} else if ( e.key === 'Escape' ) {
				finished = true;
				var span = document.createElement( 'span' );
				span.className = 'wpfb-folder-name';
				span.textContent = currentName;
				input.replaceWith( span );
			}
		} );
	};

	WPFBTree.prototype._deleteFolder = function ( item ) {
		if ( ! confirm( wpfbData.i18n.confirmDelete ) ) return;
		var self = this;

		api( 'DELETE', '/folders/' + item.dataset.id )
			.then( function () {
				item.remove();
				if ( self.activeId === item.dataset.id ) {
					self._selectNode( self.tree.querySelector( '[data-id="-1"]' ), true );
				}
			} )
			.catch( function () {} );
	};

	// Global init function so media-library.js can re-init the tree
	window.WPFBTreeInit = function ( sidebarEl ) {
		window.wpfbTree = new WPFBTree( sidebarEl );
	};

	function init() {
		var sidebar = document.getElementById( 'wpfb-sidebar' );
		if ( ! sidebar ) return;
		window.wpfbTree = new WPFBTree( sidebar );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
