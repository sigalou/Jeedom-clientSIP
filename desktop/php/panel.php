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
	<div class="col-xs-10" id="div_graphiqueDisplay">Affichage de tout les client sip	</div>
</div>
<?php include_file('desktop', 'panel', 'js', 'clientSIP');?>
<?php
include("SIPRequester.class.php");
//Script here to fetch all the info
$requester = new SIPRequester(gethostbyname('home.k-4u.nl'), 25566);

$requester->addValueToRequest('time');
$requester->addValueToRequest('weather');
$requester->addValueToRequest('uptime');
$requester->addValueToRequest('daynight');
$requester->addValueToRequest('players', 'latestdeath');
$requester->addValueToRequest('deaths');
$requester->addValueToRequest('tps');
$requester->addValueToRequest('versions');

function blockRequest($x, $y, $z, $dim, $side) {
    
    return
        [
            'x'         => $x,
            'y'         => $y,
            'z'         => $z,
            'dimension' => $dim,
            'side'      => $side,
        ];
}
/*
$requester->addValueToRequest("energy", blockRequest(-44, 63, -23, 0, "up"));
$requester->addValueToRequest("energy", blockRequest(-46, 63, -23, 0, "up"));
$requester->addValueToRequest("energy", blockRequest(-48, 63, -23, 0, "up"));

$requester->addValueToRequest("fluid", blockRequest(-44, 64, -20, 0, "up"));
$requester->addValueToRequest("fluid", blockRequest(-46, 64, -20, 0, "up"));
*/
$requester->addValueToRequest("entities", 0); //Just the overworld please
$requester->addValueToRequest("tiles", 0); //Just the overworld please
$requester->addValueToRequest("tilelist", ["dimensionid"=> 0, "name"=> "net.minecraft.tileentity.TileEntityChest"]);

$requester->doRequest();

$players = $requester->getValue('players');
$deaths = $requester->getValue('deaths');

$energyBlocks = $requester->getValue('energy');
$fluidBlocks = $requester->getValue('fluid');
$entities = $requester->getValue('entities');
$tiles = $requester->getValue('tiles');
$versions = $requester->getValue('versions');
$tileList = $requester->getValue('tilelist');

function locationToString($location){
    return "X: " . $location['x'] . " Y: " . $location['y'] . " Z: " . $location['z'];
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
        <meta name="description" content="">
        <meta name="author" content="">
        <meta charset="UTF-8">
        <title>Minecraft SIP</title>    
    </head>
    <body>
        <!--<div class="row">
          <nav class="navbar navbar-inverse">
            <div class="container">
              <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed"
                        data-toggle="collapse" data-target="#navbar"
                        aria-expanded="false" aria-controls="navbar">
                  <span class="sr-only">Toggle navigation</span>
                  <span class="icon-bar"></span>
                  <span class="icon-bar"></span>
                  <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#">My Minecraft server</a>
              </div>
              <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                  <li class="active"><a href="#">Home</a></li>
                  <li><a href="#about">About</a></li>
                  <li><a href="#contact">Contact</a></li>
                </ul>
              </div>
            </div>
          </nav>
        </div>-->
        <div class="container">
            <div class="col-md-6 col-sm-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Currently online:
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Deaths</th>
                                <th>Latest death</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($players as $player => $death) {
                                echo "<tr><td>" . $player . "</td>";
                                echo "<td>" . $deaths['LEADERBOARD'][$player] . "</td>";
                                echo "<td>" . $death . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6 col-sm-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Info
                        </div>
                    </div>
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Time</th>
                                <td><?= $requester->getValue('time')[0] ?></td>
                            </tr>
                            <tr>
                                <th>Day or night?</th>
                                <td><?= $requester->getValue('daynight') == TRUE ? "Day" : "Night" ?></td>
                            </tr>
                            <tr>
                                <th>Weather</th>
                                <td><?= $requester->getValue('weather')[0] ?></td>
                            </tr>
                            <tr>
                                <th>Uptime</th>
                                <td><?= date("H:i:s", floor($requester->getValue('uptime') / 1000)) ?></td>
                            </tr>
                            <tr>
                                <th>TPS (overworld)</th>
                                <td><?= $requester->getValue('tps')['0']['tps'] ?></td>
                            </tr>
                            <tr>
                                <th>TPS (nether)</th>
                                <td><?= $requester->getValue('tps')['-1']['tps'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
    
            <div class="col-md-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Versions
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Version</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($versions as $name => $version) {
                                if ($name != "mods") {
                                    echo "<tr>";
                                    echo "<td style='width:50%'>" . $name . "</td>";
                                    echo "<td>" . $version . "</td>";
                                    echo "</tr>";
                                }
                            }
                            foreach ($versions['mods'] as $name => $version ) {
                                echo "<tr>";
                                echo "<td style='width:50%'>" . $name . "</td>";
                                echo "<td>" . $version . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if($energyBlocks != null){ ?>
            <div class="col-md-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Blocks - Energy
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Energy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($energyBlocks as $energyBlock) {
                                echo "<tr>";
                                echo "<td style='width:10%'>" . $energyBlock['localized-name'] . "</td>";
                                echo "<td style='width:10%'>" . $energyBlock['type'] . "</td>";
                                echo "<td>";
                                $perc = ($energyBlock['stored'] / $energyBlock['capacity']) * 100;
                                ?>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-danger"
                                         role="progressbar" aria-valuenow="<?= $perc ?>"
                                         aria-valuemin="0" aria-valuemax="100"
                                         style="min-width: 2em; width: <?= $perc ?>%">
                                        <?= $energyBlock['stored'] ?>/<?= $energyBlock['capacity'] ?>
                                    </div>
                                </div>
                                <?php
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
            <?php if($fluidBlocks != null){ ?>
            <div class="col-md-12">
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Blocks - Fluid
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Fluid</th>
                                <th>Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($fluidBlocks as $fluidBlock) {
                                echo "<tr>";
                                echo "<td style='width:10%'>" . $fluidBlock['localized-name'] . "</td>";
                                echo "<td style='width:10%'>" . $fluidBlock['fluid'] . "</td>";
                                echo "<td>";
                                $perc = ($fluidBlock['stored'] / $fluidBlock['capacity']) * 100;
                                ?>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped"
                                         role="progressbar" aria-valuenow="<?= $perc ?>"
                                         aria-valuemin="0" aria-valuemax="100"
                                         style="min-width: 5em; width: <?= $perc ?>%">
                                        <?= $fluidBlock['stored'] ?>/<?= $fluidBlock['capacity'] ?>
                                    </div>
                                </div>
                                <?php
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
            
            <div class="col-md-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Entities
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($entities as $entity => $amount) {
                                echo "<tr>";
                                echo "<td style='width:80%'>" . $entity . "</td>";
                                echo "<td>" . $amount . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="col-md-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Tile Entities
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($tiles as $entity => $amount) {
                                echo "<tr>";
                                echo "<td style='width:80%'>" . $entity . "</td>";
                                echo "<td>" . $amount . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
    
            <div class="col-md-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <div class="panel-title">
                            Chests
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($tileList as $entity) {
                                echo "<tr>";
                                echo "<td style='width:80%'>" . $entity['name'] . "</td>";
                                echo "<td>" . locationToString($entity['location']) . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
