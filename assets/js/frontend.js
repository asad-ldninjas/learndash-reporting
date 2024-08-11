( function( $ ) { 'use strict';
	jQuery( document ).ready( function() {
		var LRFRONTEND ={
			init: function() {

				this.groupOnChange();
				this.createReport();
				this.filterOnChange();
				this.searchGroup();
				this.nextPagination();
				this.backPagination();
			},

			/**
			 * back pagination
			 */
			backPagination: function() {

				$( document ).on( 'click', '.lr-less-than', function() {

					$( '.lr-loader' ).show();
					let paginationPage = $( '.lr-pagination-page' ).val();
					let nextPage = parseInt( paginationPage ) - 1;
					$( '.lr-pagination-page' ).val( nextPage );

					let totalPages = $( '.lr-total-pages' ).val();

					if( nextPage == totalPages ) {
						$( '.lr-greater-than' ).hide();
					}

					let groupID = $( '.lr-group-id' ).val();
					let courseID = $( '.lr-course-id' ).val();
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : nextPage,
						'type'			  : type
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {
							$( '.lr-table-wrapper' ).remove();
							$( '.lr-filter-wrapper' ).after( jsonEncode.content );
							$( '.lr-filter' ).show();
							$( '.lr-loader' ).hide();
						}
					} );
				} );
			},

			/**
			 * next pagination
			 */
			nextPagination: function() {

				$( document ).on( 'click', '.lr-greater-than', function() {

					$( '.lr-loader' ).show();
					let paginationPage = $( '.lr-pagination-page' ).val();
					let nextPage = parseInt( paginationPage ) + 1;
					$( '.lr-pagination-page' ).val( nextPage );
					
					let totalPages = $( '.lr-total-pages' ).val();

					if( nextPage == totalPages ) {
						$( '.lr-greater-than' ).addClass( 'lr-hide' );
					}

					let groupID = $( '.lr-group-id' ).val();
					let courseID = $( '.lr-course-id' ).val();
					// console.log( courseID );
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : nextPage,
						'type'			  : type
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {
							$( '.lr-table-wrapper' ).remove();
							$( '.lr-filter-wrapper' ).after( jsonEncode.content );
							$( '.lr-filter' ).show();
							$( '.lr-loader' ).hide();
						}
					} );
				} );
			},

			/**
			 * group live search 
			 */
			searchGroup: function() {

				$( document ).on( 'select2:select', '.lr-group', function() {

					$( '.lr-course' ).html( '<option value="">Select a Course</option>' );
				} );

				setTimeout(function() {
					$( '.select2.select2-container' ).removeAttr( 'style' );
					$( '.select2.select2-container' ).addClass( 'lr-width' );
					$( '.select2-selection.select2-selection--single' ).css( 'height', '49px' );
				}, 3000 ); 

				$( document ).on( 'input', '.select2-search__field', function() {

					let self = $(this);
					let val = self.val();

					let data = {
						'action'          : 'search_group',
						'group_name'      : val
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {
							$( '.lr-group' ).html( jsonEncode.content );
						}
					} );
				} );
			},

			/**
			 * filter on change
			 */
			filterOnChange: function() {

				$( '.lr-group' ).select2();
				$( document ).on( 'click', '.lr-filter', function() {

					let self = $(this);
					let val = self.val();
					$( '.lr-table-body:odd' ).css('background-color', '#e4e4e4');
					
					if( val == 'not-started' ) {
						$( '.lr-not-started' ).show();
						$( '.lr-completed' ).hide();
						$( '.lr-in-progress' ).hide();
					}

					if( val == 'in-progress' ) {
						$( '.lr-in-progress' ).show();
						$( '.lr-completed' ).hide();
						$( '.lr-not-started' ).hide();
					}

					if( val == 'completed' ) {

						$( '.lr-completed' ).show();
						$( '.lr-in-progress' ).hide();
						$( '.lr-not-started' ).hide();
					} 

					if( val == 'Select Filter' ) {

						$( '.lr-completed' ).show();
						$( '.lr-in-progress' ).show();
						$( '.lr-not-started' ).show();
					}


				} );
			},

			/**
			 * create report
			 */
			createReport: function() {

				$( document ).on( 'click', '.lr-button', function() {

					$( '.lr-pagination-page' ).val(1);
					$( '.lr-loader' ).show();
					let groupID = $( '.lr-group' ).val();
					let courseID = $( '.lr-course' ).val();
					let paginationPage = $( '.lr-pagination-page' ).val();
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : paginationPage,
						'type'			  : type
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {
							$( '.lr-table-wrapper' ).remove();
							$( '.lr-filter-wrapper' ).after( jsonEncode.content );
							$( '.lr-filter' ).show();
							$( '.lr-loader' ).hide();
						}
					} );	

				} );
			},

			/**
			 * group on change
			 */
			groupOnChange: function() {

				$( document ).on( 'click', '.lr-course', function() {

					let groupID = $( '.lr-group' ).val();

					if( ! groupID ) {
						return false;
					}

					let selectedGroupID = $( '.lr-course' ).attr( 'data-group_id' );
					
					if( selectedGroupID == groupID ) {
						return false;
					}

					$( '.lr-loader' ).show();
					$( '.lr-course' ).attr( 'data-group_id', groupID );

					let data = {
						'action'          : 'set_course_according_to_group',
						'group_id'        : groupID
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {
							$( '.lr-course' ).html( jsonEncode.content );
							$( '.lr-loader' ).hide();
							if( ! jsonEncode.content ) {
								$( '.lr-course' ).html( '<option value="">Select a Course</option>' );
							}
						}
					} ); 
				} );
			},
		}
		LRFRONTEND.init();
	} );
} )( jQuery );