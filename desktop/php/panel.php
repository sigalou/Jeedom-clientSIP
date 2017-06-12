<?php
	if (!isConnect()) {
		throw new Exception('{{401 - Accès non autorisé}}');
	}
	$eqLogics = eqLogic::byType('clientSIP');
?>
<div style="position : fixed;height:100%;width:15px;top:50px;left:0px;z-index:998;background-color:#f6f6f6;" id="bt_displayObjectList">
	<i class="fa fa-arrow-circle-o-right" style="color : #b6b6b6;"></i>
</div>
<div class="row row-overflow" id="div_clientSIP">
	<div class="col-xs-2" id="sd_objectList" style="z-index:999">
		<div class="bs-sidebar">
			<ul id="ul_object" class="nav nav-list bs-sidenav">
				<li class="nav-header">{{Mes Clients SIP}}</li>
				<?php
					foreach ($eqLogics as $eqLogic) {
						echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName() . '</a></li>';
					}
				?>
			</ul>
		</div>
	</div>
	<div class="col-xs-10" id="div_graphiqueDisplay">
		<?php
			foreach ($eqLogics as $eqLogic) {
				echo $eqLogic->toHtml();
			}
		?>
	</div>
</div>
<?php include_file('desktop', 'panel', 'js', 'clientSIP');?>
