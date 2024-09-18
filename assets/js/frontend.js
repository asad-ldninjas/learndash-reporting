(function( $ ) { 'use strict';

    $( document ).ready( function() {

        var MPTFRONTEND = {

            init: function() {
                this.searchUser();
                this.transferMycredPoint();
                this.clearForm();
                this.nextPagination();
                this.backPagination();
                this.displayTransformForm();
            },

            /**
             * display transform form
             */
            displayTransformForm: function() {

                $( document ).on( 'change', '.mpt-recipant-id', function() {

                    let self = $(this);
                    let val = self.val();
                    let parents = self.parents( '.mtp-point-transfer-wrapper' );
                    
                    if( '' != val ) {
                        parents.find( '.mpt-search-section' ).show();
                    } else {
                        parents.find( '.mpt-search-section' ).hide();
                    }

                    let data = {
                        'action'               : 'territory_points',
                        'territory_id'         : val
                    }

                    jQuery.post( MPT.ajaxURL, data, function( response ) {

                        let jsonEncode = JSON.parse( response );
                        parents.find( '.territory-user-points' ).text( jsonEncode.content+ ' Points' );
                    } );

                } );
            },

            /**
             * back pagination
             */
            backPagination: function() {

                $( document ).on( 'click', '.mpt-left', function() {

                    let currentPage = parseFloat( $( '.mpt-right' ).attr( 'data-page' ) );

                    if( currentPage > 1 ) {

                        let nextPage = currentPage-1;
                        
                        let data = {
                            'action'               : 'mpt_pagination',
                            'page_no'              : nextPage
                        }

                        jQuery.post( MPT.ajaxURL, data, function( response ) {

                            let jsonEncode = JSON.parse( response );
                            if( jsonEncode.status == 'true' ) {
                                $( '.mpt-manager-data' ).html( jsonEncode.content );
                                $( '.mpt-right' ).attr( 'data-page', nextPage );

                                if( nextPage == 1 ) {
                                    $( '.mpt-left' ).css( 'cursor', 'not-allowed' );
                                }
                            }
                        } );
                    }
                } );
            },

            /**
             * next page pagination
             */
            nextPagination: function() {

                $( document ).on( 'click', '.mpt-right', function() {
                    
                    let self = $(this);
                    let page = parseInt( self.attr( 'data-page' ) );
                    let nextPage = page+1;

                    let data = {
                        'action'               : 'mpt_pagination',
                        'page_no'              : nextPage
                    }

                    jQuery.post( MPT.ajaxURL, data, function( response ) {

                        let jsonEncode = JSON.parse( response );
                        if( jsonEncode.status == 'true' ) {
                            $( '.mpt-manager-data' ).html( jsonEncode.content );
                            self.attr( 'data-page', nextPage );
                            $( '.mpt-left' ).css( 'cursor', 'pointer' );                               
                        }   
                    } );

                } );
            },

            /**
             * Clear the form
             */
            clearForm: function() {
                
                $( document ).on( 'click', '.mpt-clear-btn', function() {

                    let self = $(this);
                    let parent = self.parents( '.mtp-point-transfer-wrapper' );
                    let notFoundParent = self.parents( '.mpt-wrap' );
                    self.val( 'CLEARING' );
                    setTimeout( function() {
                        parent.find( '.mpt-employee-id' ).val( '' );
                        parent.find( '.mpt-f-name' ).val( '' );
                        parent.find( '.mpt-l-name' ).val( '' );
                        parent.find( '.mpt-territory-id' ).val( '' );
                        parent.find( '.mpt-data-section' ).hide();
                        self.val( 'CLEAR' );
                        self.hide();
                        notFoundParent.find( '.mpt-user-not-found' ).remove();
                    }, 2000);
                } );
            },

            /**
             * search user to point tranafer
             */
            searchUser: function() {

                $( document ).on( 'click', '.mpt-search-btn', function(e) {

                    let self = $(this);
                    let parent = self.parents( '.mtp-point-transfer-wrapper' );
                    self.parents( '.mpt-wrap' ).find( '.mpt-user-not-found' ).remove();
                    let employeID = parent.find( '.mpt-employee-id' ).val();
                    let firstName = parent.find( '.mpt-f-name' ).val();
                    let lastName = parent.find( '.mpt-l-name' ).val();
                    let territoryID = parent.find( '.mpt-territory-id' ).val();

                    self.val( 'SEARCHING..' );
                    let data = {
                        'action'               : 'search_user',
                        'mpt_nounce'           : MPT.security,
                        'emp_id'               : employeID,
                        'f_name'               : firstName,
                        'l_name'               : lastName,
                        'tri_id'               : territoryID
                    }

                    jQuery.post( MPT.ajaxURL, data, function( response ) {

                        let jsonEncode = JSON.parse( response );

                        if( jsonEncode.status == 'true' ) {

                            parent.find( '.mpt-table-result-head' ).show();
                            parent.find( '.mpt-table-result-head' ).html( '<h3>Results</h3>' );
                            parent.find( '.mpt-table-data' ).html( jsonEncode.content );
                            self.val( 'SEARCH' );
                            parent.find( '.mpt-user-not-found' ).remove();
                            parent.find( '.mpt-data-section' ).show();
                        } else {
                           
                            parent.find( '.mpt-user-not-found' ).remove();
                            parent.after( '<div class="mpt-user-not-found">No Match Found</div>' );
                            parent.find( '.mpt-search-btn' ).val( 'SEARCH' );
                            parent.find( '.mpt-data-section' ).hide();
                        }
                        parent.find( '.mpt-search-section .mpt-clear-btn' ).show();
                    } );
                } );
            },

            /**
             * transfer mycred point
             */
            transferMycredPoint: function() {

                $( document ).on( 'click', '.mpt-user-id', function() {

                    let self = $(this);
                    let mainParent = self.parents( '.mtp-point-transfer-wrapper' );
                    let userID = self.data( 'user_id' );
                    let recipeintID = $( '.mpt-recipant-id' ).val();
                    
                    if( ! recipeintID ) {
                        return false;
                    }

                    let pointValue = mainParent.find( '.mpt-point-'+userID ).val();

                    if( ! pointValue ) {
                        return false;
                    }

                    self.val( 'SEND..' );
                    
                    let data = {
                        'action'               : 'transfer_mycred_point',
                        'mpt_nounce'           : MPT.security,
                        'user_id'              : userID,
                        'value'                : pointValue,
                        'recipant_id'          : recipeintID
                    }

                    jQuery.post( MPT.ajaxURL, data, function( response ) {

                        let jsonEncode = JSON.parse( response );
                        let parent = self.parents( '.mpt-inner-wrapper' );
                        $( parent ).after( '<div class="mpt-error-message"></div>' );

                        if( jsonEncode.status == 'false' ) {
                            let errorMessage = jsonEncode.message;
                            mainParent.find( '.mpt-error-message' ).show();
                            mainParent.find( '.mpt-error-message' ).text( errorMessage );
                        } else {
                            mainParent.find( '.mpt-error-message' ).show();
                            mainParent.find( '.mpt-error-message' ).css( 'color', 'green' );
                            mainParent.find( '.mpt-error-message' ).text( 'Points awarded' );
                            let pointUserID = recipeintID;
                            let data = {
                                'action'               : 'current_user_mycred_point',
                                'user_id'              : pointUserID
                            }
                            jQuery.post( MPT.ajaxURL, data, function( response ) {

                                let jsonEncode = JSON.parse( response );

                                if( jsonEncode.status == 'true' ) {

                                    let userPoint = jsonEncode.user_point;
                                    self.parents( '.mtp-point-transfer-wrapper' ).find( '.territory-user-points' ).text( userPoint+' Points ' );
                                }
                            } );
                        }
                        self.val( 'SEND' );
                        setTimeout( function() {
                            mainParent.find( '.mpt-error-message' ).remove();
                        }, 5000);
                    } );
                } );
            },
        };

        MPTFRONTEND.init();
    });
})( jQuery );