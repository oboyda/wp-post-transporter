jQuery(function($){
	$.fn.extend({
		wpptImport: function () {
			var importData = {"action": "wppt_import"};
			var preimportForm = $("#wppt-preimport-form");
			if (preimportForm.length) {
				importData = preimportForm.serialize();
			}
			$.post(ajaxurl, importData, function (response){
				if (responseHtml = window.atob(response.html)){
					$("#wppt-import-results").html(responseHtml);
				}
				if(!response.finished && response.status){
					$().wpptImport();
				}else{
					$("#wppt-import-loader").css({"display": "none"});
					$("#wppt-import-btn").prop('disabled', false);
					$("#wppt-import-btn").css({"display": "none"});
				}
			}, "json");
		}
	});
	$("#wppt-export-btn").click(function(event){
		event.preventDefault();
		$("#wppt-export-loader").css({"display":"inline-block"});
		$(this).prop('disabled', true);
		$.post(ajaxurl, $("#wppt-export-options").serialize(), function(response){
			if(response.downloadready){
				window.open(ajaxurl+"?action=wppt_export_download", "_blank", "width=250, height=250, menubar=no, location=no");
			}else if(responseHtml = window.atob(response.html)){
				$("#wppt-export-results").html(responseHtml);
			}
			$("#wppt-export-loader").css({"display":"none"});
			$("#wppt-export-btn").prop('disabled', false);
		}, "json");
	});
	$("#wppt-import-source").change(function(){
		$("#wppt-import-options .import-input").addClass('hidden');
		$("#wppt-import-"+$(this).val()).removeClass('hidden');
	});
	$("#wppt-preimport-btn").click(function(event){
		if($("#wppt-import-source").val() != 'upload'){
			event.preventDefault();
		}
		$("#wppt-preimport-loader").css({"display":"inline-block"});
		$.post(ajaxurl, {"action":"wppt_preimport"}, function(response){
			if(responseHtml = window.atob(response.html)){
				$("#wppt-import-results").html(responseHtml);
			}
			if(response.status){
				$("#wppt-import-btn").removeClass('hidden');
			}
			$("#wppt-preimport-loader").css({"display":"none"});
		}, "json");
	});
	$("#wppt-import-btn").click(function(){
		$("#wppt-import-loader").css({"display":"inline-block"});
		$(this).prop('disabled', true);
		$().wpptImport();
	});
});
