 $('body').on('clientSIP::call', function (_event,_options) {
	 if(document[hidden]) {} else {
		Call('Un appel est en cours, voulez vous répondre');
	}
});
$('body').on('clientSIP::rtp', function (_event,_options) {
	var message=$('<div>')
		.append($('<video>')
			.attr('src','')
			.text('Votre navigateur ne supporte pas la VIDEO ou le RTP stream'));
	if(document[hidden]) {} else {
		Call(message);
	}
});
function Call(message){
	bootbox.dialog({
		title: "{{Appel en cours}}",
		message: message,
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
}
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
