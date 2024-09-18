(function( $ ) { 'use strict';

    $( document ).ready( function() {

        var MPTBACKEND = {

            init: function() {
                this.applySelect2();
                this.exportData();
                this.customRangeData();
                this.updateTerritoryUsers();
            },

            /**
             * Update territory User
             */
            updateTerritoryUsers: function() {

                $( document ).on( 'click', '.mpt-territory-button', function() {

                    var files = $(document).find('.mpt-upload-file');
                    var file = $(files)[0].files[0];
                    if ( ! file ) {
                        return false;
                    }

                    let self = $( this );
                    let parent = self.parents( '.mpt-progress-wrapper' );
                    self.text( 'Updating...' );
                    $( '.mpt-progress-bar' ).show();
                    let progressPercent = $( '.mpt-progress-bar .progress-text' ).text();
                    let start = parseInt( parent.attr( 'data-start' ) );
                    let end = parseInt( parent.attr( 'data-end' ) );
                    let reader = new FileReader();
                    reader.onload = function(e) {
                        // Parse the CSV data and create an array of user data
                        let user_data = [];
                        let csv = e.target.result;
                        let lines = csv.split("\n");
                        for( let i = 0; i < lines.length; i++) {
                            let line = lines[i].trim();
                            if (line) {
                                let parts = line.split(",");
                                let userName = parts[0];
                                if( 'Username' != userName ) {
                                    let territoryUsers = parts;
                                    user_data.push( [territoryUsers] );
                                }
                            }
                        }

                        let limitedUserArray = user_data.slice( start, end );
                        let ajaxUrl = MPT.ajaxURL;
                        let totalItems  = user_data.length;

                        if( end > user_data.length + 100 ) {
                            return false;
                        }

                        let data = {

                            'action'    : 'update_territory_users',
                            'user_data' : limitedUserArray
                        }
                        
                        $.post( ajaxUrl, data, function( response ) {

                            let resp = JSON.parse(response);
                            
                            if( resp.status == 'true' ) {

                                parent.attr( 'data-start', start + 100 );
                                parent.attr( 'data-end', end + 100 );
                                $( '.mpt-territory-button' ).click();

                                let updated_data = parent.attr('data-start');
                                let progressPercent = 0;
                                if ( updated_data > totalItems ) {
                                    progressPercent = 100;
                                } else {
                                    progressPercent = ( updated_data / totalItems ) * 100;
                                }

                                $( '.mpt-progress-bar .progress' ).text(progressPercent.toFixed(2) + "%");
                                $('.mpt-progress-bar .progress').css('width', progressPercent + '%');
                                $( '.mpt-progress-bar .progress' ).css( {
                                    'background'  : '#4CAF50',
                                    'width'       : progressPercent + '%',
                                    'color'       : '#fff',
                                    'font-weight' : 'bold',
                                    'text-align'  : 'center'
                                } );
                            }
                        } );
                    };

                    reader.readAsText(file);
                } );
            },

            /**
             * custom range data
             */
            customRangeData: function() {

                $( document ).on( 'click', '.mpt-search-btn', function() {

                    let startDate = $( '.mpt-start-date' ).val();
                    if( ! startDate ) {
                        return false;
                    }
                    let endDate = $( '.mpt-end-date' ).val();
                    let ref = $( '#myCRED-reference-filter' ).val();
                    let order = $( '#myCRED-order-filter' ).val();
                    let user = $( '#myCRED-user-filter' ).val();

                    let redirectUrl = MPT.custom_date_url;

                    if( ref ) {
                        redirectUrl = redirectUrl+'&ref='+ref;
                    }

                    if( order ) {
                        redirectUrl = redirectUrl+'&order='+order;
                    }

                    if( user ) {
                        redirectUrl = redirectUrl+'&user='+user;   
                    }

                    let data = {
                        'action'                 : 'custom_date_range',
                        'start_date'             : startDate,
                        'end_date'               : endDate
                    }

                    jQuery.post( MPT.ajaxURL, data, function( response ) {
                        window.location.href = redirectUrl;
                    } );
                } );
            },

            /**
             * Download data 
             */
            exportData: function() {

                $( document ).on( 'click', '.mpt-export-button', function() {

                    let currentURL = window.location.href;
                    var urlParams = new URLSearchParams( currentURL );
                    var showValue = urlParams.get('show');
                    let ref = $( '#myCRED-reference-filter' ).val();
                    let order = $( '#myCRED-order-filter' ).val();
                    
                    if( showValue == null ) {
                        showValue = '';
                    }

                    if( ! showValue ) {
                        return false;
                    }

                    let startDate = '';
                    let endDate = '';

                    if( 'daterange' == showValue ) {

                        startDate = $( '.mpt-start-date' ).val();
                        endDate = $( '.mpt-end-date' ).val();
                    }

                    let data = {
                        'action'                 : 'download_log_data',
                        'start_date'             : startDate,
                        'end_date'               : endDate,
                        'reference'              : ref,
                        'order'                  : order,
                        'show'                   : showValue
                    }

                    jQuery.post( MPT.ajaxURL, data, function( response ) {
                        location.reload();
                    } );
                } );
            },

            /**
             * Apply select2
             */
            applySelect2: function() {
                $( '.mpt-select-2' ).select2();  
                $( '.mpt-users-select-2' ).select2({
                    placeholder: 'Select a user'
                });
            },
        };

        MPTBACKEND.init();
    });
})( jQuery );