 $('body').on('clientSIP::call', function (_event,_options) {
	bootbox.dialog({
		title: "{{Ajout d'une nouvelle condition}}",
		message: 'Un appel est en cours, voulez vous répondre',
		buttons: {
			"Racrocher": {
				className: "btn-danger",
				callback: function () {
					updateCache('Racrocher');
				}
			},
			success: {
				label: "Décrocher",
				className: "btn-success",
				callback: function () {
					updateCache('Decrocher');
				}
			},
		}
	});
});
$('body').on('clientSIP::rtp', function (_event,_options) {
});
function updateCache(reponse){
	$.ajax({
		type: 'POST',            
		async: false,
		url: 'plugins/clientSIP/core/ajax/clientSIP.ajax.php',
		data:{
			action: 'updateCache',
			value:reponse
		},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {},
		success: function(data) {
			if (!data.result)
				$('#div_alert').showAlert({message: 'Aucun message reçu', level: 'error'});
		}
	});
}
