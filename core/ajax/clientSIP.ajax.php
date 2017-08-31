<?php
try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    	include_file('core', 'authentification', 'php');
	include_file('core', 'dpt', 'class', 'eibd');
    	if (!isConnect('admin')) {
        	throw new Exception(__('401 - Accès non autorisé', __FILE__));
    	}
	if (init('action') == 'updateCmd') {
   		$cmd=cmd::byId(init('id'));
    		if(is_object($cmd)){
		  	log::add('clientSIP','debug','Mise a jours du status: '.init('value'));
      			$cmd->event(init('value'));
		  	ajax::success(true);
		}
		ajax::success(false);
	}
   throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
