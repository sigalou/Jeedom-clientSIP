if((!isset(userProfils.doNotAutoHideMenu) || userProfils.doNotAutoHideMenu != 1) && !jQuery.support.touch){
	$('#sd_objectList').hide();
	$('#div_graphiqueDisplay').removeClass('col-xs-10').addClass('col-xs-12');
	$('#bt_displayObjectList').on('mouseenter',function(){
		var timer = setTimeout(function(){
			$('#bt_displayObjectList').find('i').hide();
			$('#div_graphiqueDisplay').addClass('col-xs-10').removeClass('col-xs-12');
			$('#sd_objectList').show();
			$(window).resize();
		}, 100);
		$(this).data('timerMouseleave', timer)
	}).on("mouseleave", function(){
		clearTimeout($(this).data('timerMouseleave'));
	});
	$('#sd_objectList').on('mouseleave',function(){
		var timer = setTimeout(function(){
			$('#sd_objectList').hide();
			$('#bt_displayObjectList').find('i').show();
			$('#div_graphiqueDisplay').removeClass('col-xs-10').addClass('col-xs-12');
			setTimeout(function(){
				$(window).resize();
			},100);
			setTimeout(function(){
				$(window).resize();
			},300);
			setTimeout(function(){
				$(window).resize();
			},500);
		}, 300);
		$(this).data('timerMouseleave', timer);
	}).on("mouseenter", function(){
		clearTimeout($(this).data('timerMouseleave'));
	});
}
