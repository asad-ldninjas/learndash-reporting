( function( $ ) { 'use strict';
	jQuery( document ).ready( function() {
		var LRFRONTEND ={
			init: function() {

				this.groupOnChange();
				this.createReport();
				this.filterOnChange();
				// this.searchGroup();
				this.nextPagination();
				this.backPagination();
				this.lastPage();
				this.firstPage();
				this.downloadCSV();
				this.ascendingData();
				this.descendingData();
				this.displayGroupOptions();
				this.loadMoreGroupOption();
				this.sortGroupAlphabatically();
			},

			/**
			 * create a function to sort group alphabatically
			 */
			sortAlphabetically: function() {

				var $container = $('.lr-inner-wrapper');
				var $options = $container.find('.lr-group-option').get();
				$options.sort(function(a, b) {
					var textA = $(a).text().toUpperCase(); 
					var textB = $(b).text().toUpperCase();
					return textA.localeCompare(textB);
				});

				$container.find('.lr-group-option').remove();
				$options.forEach(function(option) {
					$container.append(option);
				});

				let html = $( '.lr-load-more' ).html();
				
				if( html ) {

					let dataNumber = parseInt( $( '.lr-load-more' ).attr( 'data-number' ) );
					let totalNumber = parseInt( $( '.lr-load-more' ).attr( 'date-total-data' ) );
					$( '.lr-load-more' ).remove();

					if( dataNumber * 5 < totalNumber ) {
						let newHtml = '<div class="lr-load-more" data-number="'+dataNumber+'" data-total_number="'+totalNumber+'">'+html+'</div>'; 
						$( '.lr-inner-wrapper' ).append( newHtml );
					}
				}
			},

			/**
			 * sort group alphabatically
			 */
			sortGroupAlphabatically: function() {
				LRFRONTEND.sortAlphabetically();
			},

			/**
			 * load more group option
			 */
			loadMoreGroupOption: function() {

				$(document).on('click', function(event) {
					if ( ! $( event.target ).closest( '.lr-group-dropdown-wrapper').length ) {
						$( '.lr-inner-wrapper' ).hide();
					}
				} );

				$( document ).on( 'click', '.lr-load-more', function() {

					let self = $(this);
					self.text( 'Load more ...' );
					let dataCount = self.attr( 'data-number' ); 
					
					dataCount = parseInt( dataCount ) + 1;
					self.attr( 'data-number', dataCount );

					let data = {
						'action'          : 'load_group_option',
						'count'		      : dataCount,
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {
						
						let jsonEncode = JSON.parse( response );
						if( jsonEncode.status == 'true' ) {

							$( '.lr-load-more' ).text( 'Load more' );
							$( '.lr-load-more' ).before( jsonEncode.content );
							let updatedNumber = parseInt( $( '.lr-load-more' ).attr( 'data-number' ) ) * 5;
							let totalData = parseInt( $( '.lr-load-more' ).attr( 'date-total-data' ) );
							if( totalData < updatedNumber || updatedNumber == totalData ) {
								$( '.lr-load-more' ).remove();
							} 

							LRFRONTEND.sortAlphabetically();
						}					
					} );
				} );
			},

			/**
			 * display group option
			 */
			displayGroupOptions: function() {

				$( document ).on( 'click', '.lr-group-dropdown-header', function() {

					$( '.lr-inner-wrapper' ).toggle();
				} );
			},

			/**
			 * Ascending order
			 */
			descendingData: function() {

				$( document ).on( 'click', '.lr-arrow-down', function() {

					let self = $(this);
					let coulmnNo = self.attr( 'data-coulmn' );
					
					var table, rows, switching, i, x, y, shouldSwitch;
					table = document.getElementById("lr-table");
					switching = true;

					while (switching) {
						switching = false;
						rows = table.rows;
						for (i = 1; i < (rows.length - 1); i++) {

							shouldSwitch = false;

							x = rows[i].getElementsByTagName("TD")[coulmnNo];
							y = rows[i + 1].getElementsByTagName("TD")[coulmnNo];

							if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
								shouldSwitch = true;
								break;
							}
						}
						if (shouldSwitch) {
							rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
							switching = true;
						}
					}
				} );
			},

			/**
			 * Ascending data
			 */
			ascendingData: function() {

				$( document ).on( 'click', '.lr-arrow-up', function() {

					let self = $(this);
					let coulmnNo = self.attr( 'data-coulmn' );
					
					var table, rows, switching, i, x, y, shouldSwitch;
					table = document.getElementById("lr-table");
					switching = true;

					while (switching) {
						switching = false;
						rows = table.rows;
						for (i = 1; i < (rows.length - 1); i++) {

							shouldSwitch = false;

							x = rows[i].getElementsByTagName("TD")[coulmnNo];
							y = rows[i + 1].getElementsByTagName("TD")[coulmnNo];

							if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {

								shouldSwitch = true;
								break;
							}
						}
						if (shouldSwitch) {

							rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
							switching = true;
						}
					}
				} );
			},

			/**
			 * download csv
			 */
			downloadCSV: function() {

				$( document ).on( 'click', '.lr-csv-btn', function() {

					$( '.lr-csv-btn' ).text( 'Download Report ...' );
					// let groupID = $( '.lr-group' ).val();
					let groupID = $( '.lr-group-dropdown-header' ).attr( 'data-selected-group' );

					let courseID = $( '.lr-course' ).val();
					let type = $( '.lr-filter' ).val();
					let dataType = $( '.lr-button' ).attr( 'data-type' );

					let data = {
						'action'          : 'download_csv_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'type'			  : type,
						'data_type'		  : dataType
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {
						$( '.lr-csv-btn' ).text( 'Download Report' );
						location.reload();
					} );
				} );
			},

			/**
			 * first page data
			 */
			firstPage: function() {

				$( document ).on( 'click', '.lr-skipback', function() {

					$( '.lr-loader' ).show();
					let dataType = $( '.lr-button' ).attr( 'data-type' );
					let nextPage = 1;
					$( '.lr-pagination-page' ).val( nextPage );
					
					let totalPages = $( '.lr-total-pages' ).val();

					if( nextPage == totalPages ) {
						$( '.lr-greater-than' ).addClass( 'lr-hide' );
					}

					let groupID = $( '.lr-group-id' ).val();
					let courseID = $( '.lr-course-id' ).val();
					
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : nextPage,
						'type'			  : type,
						'data_type'		  : dataType
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
			 * last page data
			 */
			lastPage: function() {

				$( document ).on( 'click', '.lr-skipforward', function() {

					$( '.lr-loader' ).show();
					let dataType = $( '.lr-button' ).attr( 'data-type' );
					let nextPage = parseInt( $( '.lr-last-page' ).val() );
					$( '.lr-pagination-page' ).val( nextPage );
					
					let totalPages = $( '.lr-total-pages' ).val();

					if( nextPage == totalPages ) {
						$( '.lr-greater-than' ).addClass( 'lr-hide' );
					}

					let groupID = $( '.lr-group-id' ).val();
					let courseID = $( '.lr-course-id' ).val();
					
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : nextPage,
						'type'			  : type,
						'data_type'		  : dataType
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
			 * back pagination
			 */
			backPagination: function() {

				$( document ).on( 'click', '.lr-less-than', function() {

					$( '.lr-loader' ).show();
					let dataType = $( '.lr-button' ).attr( 'data-type' );
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
						'type'			  : type,
						'data_type'		  : dataType
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
					let dataType = $( '.lr-button' ).attr( 'data-type' );
					let paginationPage = $( '.lr-pagination-page' ).val();
					let nextPage = parseInt( paginationPage ) + 1;
					$( '.lr-pagination-page' ).val( nextPage );
					
					let totalPages = $( '.lr-total-pages' ).val();

					if( nextPage == totalPages ) {
						$( '.lr-greater-than' ).addClass( 'lr-hide' );
					}

					let groupID = $( '.lr-group-id' ).val();
					let courseID = $( '.lr-course-id' ).val();
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : nextPage,
						'type'			  : type,
						'data_type'	 	  : dataType
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

					$( '.lr-error-message' ).remove();
					$( '.lr-pagination-page' ).val(1);
					$( '.lr-loader' ).show();
					
					let dataType = $( '.lr-button' ).attr( 'data-type' );
					let groupID = $( '.lr-group-dropdown-header' ).attr( 'data-selected-group' );
					if( ! groupID ) {
						
						$( '.lr-loader' ).hide();
						let html = '';
						html += '<div class="lr-error-message">Please select a group to generate a report</div>';
						$( '.lr-main-wrapper' ).after( html );
						return false;
					}
					let courseID = $( '.lr-course' ).val();
					let paginationPage = $( '.lr-pagination-page' ).val();
					let type = $( '.lr-filter' ).val();

					let data = {
						'action'          : 'create_report',
						'group_id'        : groupID,
						'course_id'		  : courseID,
						'paged'			  : paginationPage,
						'type'			  : type,
						'data_type'		  : dataType
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {

							$( '.lr-table-wrapper' ).remove();
							$( '.lr-filter-wrapper' ).after( jsonEncode.content );							
							$( '.lr-filter' ).show();
							$( '.lr-loader' ).hide();
						}

						let tableContainsTd = $('#lr-table').find('td').length > 0;
						
						if( ! tableContainsTd ) {
							let newRow = $('<tr>').append(
								$('<td colspan="7">').text('There are no results for your search terms')
							);
							$( '#lr-table' ).append(newRow);
						}
					} );	
				} );
			},

			/**
			 * group on change
			 */
			groupOnChange: function() {

				$( document ).on( 'click', '.lr-group-option', function() {

					$( '.lr-inner-wrapper' ).hide();

					let self = $(this);

					let groupID = self.attr( 'data-group_id' );
					$( '.lr-group-dropdown-header' ).attr( 'data-selected-group', groupID );

					$( '.lr-select-text-wrap' ).html( self.text() );

					if( ! groupID ) {
						return false;
					}

					$( '.lr-loader' ).show();

					let data = {
						'action'          : 'set_course_according_to_group',
						'group_id'        : groupID
					};

					jQuery.post( LR.ajaxURL, data, function( response ) {

						let jsonEncode = JSON.parse( response );

						if( jsonEncode.status == 'true' ) {
							$( '.lr-course' ).html( jsonEncode.content );
							$( '.lr-loader' ).hide();
						}
					} ); 
				} );
			},
		}
		LRFRONTEND.init();
	} );
} )( jQuery );