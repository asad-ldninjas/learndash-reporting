( function( $ ) { 'use strict';
	jQuery( document ).ready( function() {
		var LRFRONTEND ={
			init: function() {

				this.groupOnChange();
				this.createReport();
				this.filterOnChange();
				this.searchGroup();
			},

			/**
			 * group live search 
			 */
			searchGroup: function() {

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


				} );
			},

			/**
			 * create report
			 */
			createReport: function() {

				$( document ).on( 'click', '.lr-button', function() {

					$( '.lr-loader' ).show();
					let groupID = $( '.lr-group' ).val();
					let courseID = $( '.lr-course' ).val();
					
					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID
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