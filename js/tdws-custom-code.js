var tdws_web_link_import_datas = [];
var tdws_web_link_faild_datas =[];
var tdws_web_link_success_datas =[];
var tdws_web_link_success_cnt = 0, tdws_web_link_error_cnt = 0;
jQuery(document).ready(function(){
	jQuery(document).on( 'submit', '.tdws_import_form', function (e) {
		e.preventDefault(); 
		var regex = /^([a-zA-Z0-9\s_\\.\-():])+\.csv$/;
		if (!regex.test(jQuery(".tdws_import_file").val().toLowerCase())) {
			alert("Please upload a valid CSV file.");
			return false;
		}
		tdws_web_link_faild_datas = [];
		tdws_web_link_success_datas = [];
		tdws_web_link_success_cnt = 0;
		tdws_web_link_error_cnt = 0;
		var p_this = jQuery(this);
		p_this.parent().find('.tdws-error-box').remove();
		jQuery('.tdws-table-wrap').remove();		
		p_this.parent().find('.tdws-success-box').remove();
		p_this.find('.tdws-web-loader').removeClass('tdws-hide');	
		jQuery('.tdws-cnt-box,.tdws_download_error,.tdws_download_success').addClass('tdws-hide');
		jQuery('.tdws-cnt-box .tdws-error-cnt span').text(tdws_web_link_error_cnt);
		jQuery('.tdws-cnt-box .tdws-success-cnt span').text(tdws_web_link_success_cnt);
		var per_item = jQuery('.tdws_import_per_Item').val();
		if( per_item == 0 || per_item == '' ){
			per_item = 5;
		}

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
					var tdws_itemsPerPage = parseInt(per_item);
					var tdws_currentPage = 1;
					var tdws_totalItems = tdws_web_link_import_datas.length;
					var tdws_totalPages = Math.ceil( tdws_totalItems / tdws_itemsPerPage );


					tdws_save_web_link_item( tdws_currentPage, tdws_web_link_import_datas, tdws_totalPages, tdws_itemsPerPage  );
				}else if( response.type == 'fail' ){
					p_this.before('<div class="tdws-error-box notice notice-error"><p>'+response.msg+'</p></div>');
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

	jQuery(document).on( 'click', '.tdws_download_error_file', function() {					
		tdws_downloadCSV( tdws_web_link_faild_datas, 'failedRecords.csv' );
	});

	jQuery(document).on( 'click', '.tdws_download_success_file', function() {					
		tdws_downloadCSV( tdws_web_link_success_datas, 'successRecords.csv' );
	});

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


function tdws_save_web_link_item( import_index, tdws_web_link_import_datas, tdws_totalPages, tdws_itemsPerPage ){	

	if( import_index <= tdws_totalPages ){	

		const tdws_start = ( import_index - 1 ) * tdws_itemsPerPage;
		const tdws_end = tdws_start + tdws_itemsPerPage;
		const tdws_vendor_link_list = tdws_web_link_import_datas.slice(tdws_start, tdws_end);

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : tdwsCustomAjax.ajaxurl,
			data : {action: "tdws_save_web_link_by_file", nonce : tdwsCustomAjax.nonce, web_link_items : tdws_vendor_link_list },
			success: function(data){				

				if( data.type == 'error' ){
					tdws_web_link_error_cnt = tdws_web_link_error_cnt + tdws_itemsPerPage;
					jQuery('.tdws-cnt-box .tdws-error-cnt span').text(tdws_web_link_error_cnt);
					tdws_web_link_faild_datas = [...tdws_web_link_faild_datas, ...tdws_vendor_link_list];
				}else{
					if( data.items !== undefined ){
						jQuery(data.items).each(function ( d, f ) {
							if( f.type == 'success' ){
								if( f.link_item !== undefined ){
									tdws_web_link_success_cnt++;									
									tdws_web_link_success_datas.push( f.link_item );			
								}
							}else{
								tdws_web_link_faild_datas.push( f.link_item );			
								tdws_web_link_error_cnt++;
							}							
						});
					}		
					jQuery('.tdws-cnt-box .tdws-success-cnt span').text(tdws_web_link_success_cnt);
					jQuery('.tdws-cnt-box .tdws-error-cnt span').text(tdws_web_link_error_cnt);
				}
				import_index++;
				setTimeout(function(){
					tdws_save_web_link_item( import_index, tdws_web_link_import_datas, tdws_totalPages, tdws_itemsPerPage );
				},100);
			},
			error: function(e) {
				tdws_web_link_faild_datas = [...tdws_web_link_faild_datas, ...tdws_vendor_link_list];
				import_index++;
				setTimeout(function(){
					tdws_save_web_link_item( import_index, tdws_web_link_import_datas, tdws_totalPages, tdws_itemsPerPage );
				},100);
				tdws_web_link_error_cnt = tdws_web_link_error_cnt + tdws_itemsPerPage;
				jQuery('.tdws-cnt-box .tdws-error-cnt span').text(tdws_web_link_error_cnt);
			}          
		});
	}else{		
		jQuery('.tdws_import_form .tdws-web-loader').addClass('tdws-hide');
		jQuery('.tdws_import_form').before('<div class="tdws-success-box notice notice-success"><p>All Data Imported Successfully...</p></div>');

		jQuery('.tdws_import_form')[0].reset();
		if( tdws_web_link_success_datas.length > 0 ){
			jQuery('.tdws_download_success').removeClass('tdws-hide');
		}
		if( tdws_web_link_faild_datas.length > 0 ){
			jQuery('.tdws_download_error').removeClass('tdws-hide');			
			var tdws_table_html = '<div class="tdws-table-wrap"><table width="100%" border="1" cellpadding="5" cellspacing="5">\
			<thead><tr><th>Product SKU/URL</th><th>Vendor Links</th></tr></thead>\
			<tbody>';
            // Iterate through the data array and create table rows
			jQuery(tdws_web_link_faild_datas).each(function(index, item) {
				var tdws_sku = '',tdws_vendor_link = '';
				if( item[0] !== undefined ){
					tdws_sku = item[0];
				}
				if( item[1] !== undefined ){
					tdws_vendor_link = item[1];
				}
				tdws_table_html += '<tr><td>'+tdws_sku+'</td><td>'+tdws_vendor_link+'</td></tr>';
			});
			tdws_table_html += '</tbody></table></div>'
			jQuery('.tdws_import_form').append(tdws_table_html);

		}
	}
}

function tdws_downloadCSV(array, filename = 'data.csv') {
	const csv_header = ['SKU/URL', 'Vendor Links'];
	const csv_data = tdws_arrayToCSV([csv_header, ...array]);
	const blob = new Blob([csv_data], { type: 'text/csv;charset=utf-8;' });
	const link = document.createElement('a');
    if (link.download !== undefined) { // feature detection
    	const url = URL.createObjectURL(blob);
    	link.setAttribute('href', url);
    	link.setAttribute('download', filename);
    	link.style.visibility = 'hidden';
    	document.body.appendChild(link);
    	link.click();
    	document.body.removeChild(link);
    }
}

function tdws_arrayToCSV(array) {

	return array.map(row => 
		row.map(cell => 
			`"${String(cell).replace(/"/g, '""')}"`
			).join(',')
		).join('\n');
}