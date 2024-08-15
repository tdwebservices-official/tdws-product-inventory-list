var tdws_web_link_import_datas = [];
var tdws_web_link_success_cnt = 0, tdws_web_link_error_cnt = 0;
jQuery(document).ready(function(){
	jQuery(document).on( 'submit', '.tdws_import_form', function (e) {
		e.preventDefault(); 
		var regex = /^([a-zA-Z0-9\s_\\.\-:])+(.csv|.txt)$/;
		if (!regex.test(jQuery(".tdws_import_file").val().toLowerCase())) {
			alert("Please upload a valid CSV file.");
			return false;
		}
		var p_this = jQuery(this);
		p_this.parent().find('.tdws-error-box').remove();
		p_this.parent().find('.tdws-success-box').remove();
		p_this.find('.tdws-web-loader').removeClass('tdws-hide');	
		p_this.parent().find('.tdws-error-box').remove();
		var form_data = new FormData(jQuery(this)[0]);
		form_data.append( 'action', 'tdws_import_read_web_link_data' );
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : tdwsCustomAjax.ajaxurl,						
			type: 'POST',
       		contentType: false,
        	processData: false,
        	data: form_data,
			success: function(response) {
				if( response.type == 'success' ){
					tdws_web_link_import_datas = response.productData;
					jQuery('.tdws-cnt-box').removeClass('tdws-hide');
					tdws_save_web_link_item( 0, tdws_web_link_import_datas );
				}else if( response.type == 'fail' ){
					p_this.before('<div class="tdws-error-box notice notice-error"><p>'+response.msg+'</p></div>');
					setTimeout(function(){
						p_this.parent().find('.tdws-error-box').remove();
					},1000);
					p_this.find('.tdws-web-loader').addClass('tdws-hide');	
				}	
			},
			error: function(response) {
				p_this.find('.tdws-web-loader').addClass('tdws-hide');	
			},
		});
	} );	

	jQuery(document).on( 'click', '.tdws_save_web_link', function(e){
		e.preventDefault(); 
		var p_this = jQuery(this);
		var post_id = jQuery(this).attr("data-id");
		var tdws_web_link = jQuery(this).closest('.tdws-weblink-box').find('textarea').val();
		p_this.siblings('.tdws-web-loader').removeClass('tdws-hide');	
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : tdwsCustomAjax.ajaxurl,
			data : {action: "tdws_save_web_link", post_id : post_id, nonce : tdwsCustomAjax.nonce, tdws_web_link : tdws_web_link },
			success: function(response) {
				if( response.type == 'success' ){
					p_this.closest('tr').addClass('tdws-success-alert');
					setTimeout(function(){
						p_this.closest('tr').removeClass('tdws-success-alert');
					},1000);
				}
				if( response.type == 'fail' ){
					p_this.closest('tr').addClass('tdws-error-alert');
					setTimeout(function(){
						p_this.closest('tr').removeClass('tdws-error-alert');
					},1000);
				}
				p_this.siblings('.tdws-web-loader').addClass('tdws-hide');
			},
			error: function(response) {
				p_this.closest('tr').addClass('tdws-error-alert');
				setTimeout(function(){
					p_this.closest('tr').removeClass('tdws-error-alert');
				},1000);
				p_this.siblings('.tdws-web-loader').addClass('tdws-hide');
			},
		});  
	} );

	jQuery(document).on( 'change keyup keypress keydown', '.tdws-weblink-box textarea,.tdws-vendor-link-wrap .tdws_web_link', function(e){
		var value = jQuery(this).val();
		value = tdws_remove_all_query_string(value);
		jQuery(this).val(value);
	} );

});

function tdws_remove_all_query_string(web_links){
	var new_web_link = [];
	if( web_links.trim() != '' ){
		var web_link_arr = web_links.split(',');    
		if( web_link_arr.length > 0 ){
			jQuery(web_link_arr).each(function ( k, v_link ) {
				var v_link = v_link.split('?');
				new_web_link.push(v_link[0]);
			});
		}
	}
	var new_web_link_str = new_web_link.join(',');
	return new_web_link_str;
}


function tdws_save_web_link_item( import_index, tdws_web_link_import_datas ){	
	if( import_index < tdws_web_link_import_datas.length ){		
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : tdwsCustomAjax.ajaxurl,
			data : {action: "tdws_save_web_link_by_file", nonce : tdwsCustomAjax.nonce, web_link_items : tdws_web_link_import_datas[import_index] },
			success: function(data){
				import_index++;
				setTimeout(function(){
					tdws_save_web_link_item( import_index, tdws_web_link_import_datas );
				},100);
				tdws_web_link_success_cnt++;
				jQuery('.tdws-cnt-box .tdws-success-cnt span').text(tdws_web_link_success_cnt);
			},
			error: function(e) {
				import_index++;
				setTimeout(function(){
					tdws_save_web_link_item( import_index, tdws_web_link_import_datas );
				},100);
				tdws_web_link_error_cnt++;
				jQuery('.tdws-cnt-box .tdws-error-cnt span').text(tdws_web_link_error_cnt);
			}          
		});
	}else{		
		jQuery('.tdws_import_form .tdws-web-loader').addClass('tdws-hide');
		jQuery('.tdws_import_form').before('<div class="tdws-success-box notice notice-success"><p>All Data Imported Successfully...</p></div>');
		setTimeout(function () {
			window.location.href = window.location.href;
		},2500);
	}
}