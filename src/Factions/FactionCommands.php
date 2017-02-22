<?php

namespace Factions;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
class FactionCommands {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('f'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("§6- §3Please use §e/f help §3for a list of commands"));
					return true;
				}
				if(count($args == 2)) {
					
					/////////////////////////////// CREATE ///////////////////////////////
					
					if($args[0] == "create") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f create <faction name>"));
							return true;
						}
						if(!(isset($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cNames can only include letters or numbers"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cThis name is restricted"));
							return true;
						}
						if($this->plugin->factionExists($args[1]) == true ) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cThis name is already in use"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cThis name is too long"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou are already in a faction"));
							return true;
						} else {
							$factionName = $args[1];
							$player = strtolower($player);
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
                            
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
							$sender->sendMessage($this->plugin->formatMessage("§6- §aFaction successfully created", true));
							return true;
						}
					}
					
					/////////////////////////////// INVITE ///////////////////////////////
					
					if($args[0] == "invite") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f invite <player>"));
							return true;
						}
						if($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cFaction is full. Kick some for spaces."));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if(!$invited instanceof Player) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is  offline"));
							return true;
						}
						if($this->plugin->isInFaction($args[1]) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is already in a faction"));
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite") == true) {
                            if(!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))){
							    $sender->sendMessage($this->plugin->formatMessage("§6- §cOnly leader and officers can invite"));
							    return true;
                            } 
						}
						
						if($invited->isOnline() == true) {
							$factionName = $this->plugin->getPlayerFaction($player);
							$invitedName = $invited->getName();
							$rank = "Member";
								
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
							$stmt->bindValue(":player", strtolower($invitedName));
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":invitedby", $sender->getName());
							$stmt->bindValue(":timestamp", time());
							$result = $stmt->execute();
	
							$sender->sendMessage($this->plugin->formatMessage("§6- §e$invitedName §ahas been invited", true));
							$invited->sendMessage($this->plugin->formatMessage("§6- §3You have been invited to §e$factionName §3. Use '/f accept' or '/f deny'", true));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is offline"));
						}
					}
					
					/////////////////////////////// LEADER ///////////////////////////////
					
					if($args[0] == "leader") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f leader <player>"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to use this"));
                            return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to use this"));
                            return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is not in faction"));
                            return true;
						}		
						if(!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is offline"));
                            return true;
						}
				        $factionName = $this->plugin->getPlayerFaction($player);
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
				        $stmt->bindValue(":player", $player);
				        $stmt->bindValue(":faction", $factionName);
				        $stmt->bindValue(":rank", "Member");
				        $result = $stmt->execute();
	
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
				        $stmt->bindValue(":player", strtolower($args[1]));
				        $stmt->bindValue(":faction", $factionName);
				        $stmt->bindValue(":rank", "Leader");
				        $result = $stmt->execute();
	
	
				        $sender->sendMessage($this->plugin->formatMessage("You are no longer leader", true));
				        $this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§6- §aYou are now leader of §e$factionName §3!",  true));
				}
					
					/////////////////////////////// PROMOTE ///////////////////////////////
					
					if($args[0] == "promote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f promote <player>"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is not in faction"));
							return true;
						}
						if($this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is already Officer"));
							return true;
						}
                        if(!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is offline!"));
                            return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Officer");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayer($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("§6- §e" . $player->getName() . " §3has been promoted to Officer!", true));
						$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§6- §cYou were promoted to officer of§e $factionName!", true));
						}
					
					/////////////////////////////// DEMOTE ///////////////////////////////
					
					if($args[0] == "demote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f demote <player>"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to use this"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is not in faction"));
							return true;
						}
						if(!$this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is already Member"));
							return true;
						}
                        if(!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is offline"));
                            return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayer($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("" . $player->getName() . " has been demoted to Member.", true));
						$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§6- §You were demoted to member of§e $factionName", true));
					}
					
					/////////////////////////////// KICK ///////////////////////////////
					
					if($args[0] == "kick") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f kick <player>"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to use this"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cPlayer is not in faction"));
							return true;
						}
						$kicked = $this->plugin->getServer()->getPlayer($args[1]);
						$factionName = $this->plugin->getPlayerFaction($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage($this->plugin->formatMessage("§6- §2You have kicked §a$args[1]§2!", true));
                        $this->plugin->subtractFactionPower($factionName,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
						$players[] = $this->plugin->getServer()->getOnlinePlayers();
						if(in_array($args[1], $players) == true) {
							$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§6- §cYou have been kicked from§e $factionName, true"));
							return true;
						}
					}
					
					/////////////////////////////// INFO ///////////////////////////////
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("§6- §cFaction does not exist"));
								return true;
							}
							$faction = $args[1];
                            $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							$sender->sendMessage("§6Leader:§3 $leader");
							$sender->sendMessage("§6Players:§f $numPlayers");
							$sender->sendMessage("§6FPower:§f $power");
							$sender->sendMessage("§6Message:§e $message");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						} else {
							$faction = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							$sender->sendMessage("§6Leader:§3 $leader");
							$sender->sendMessage("§6Players:§f $numPlayers");
							$sender->sendMessage("§6FPower:§f $power");
							$sender->sendMessage("§6Message:§e $message");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						}
					}
					if(strtolower($args[0]) == "help") {//forceunclaim
						if(!isset($args[1]) || $args[1] == 1) {
							$sender->sendMessage("§f-+ §bFactions Help Page 1/5 §f" . TextFormat::WHITE . "\n§6/f about\n§6/f accept §f- Accept an invite to a faction\n§6/f overclaim §f- Overclaim land of an opposing faction\n§6/f claim §f- Claim land for your faction\n§6/f create <name> §f- Create a faction\n§6/f disband §f- Disband your faction\n§6/f demote <player> §f- Demote a player to member\n§6/f deny §f- Deny an invite to a faction");
							return true;
						}
						if($args[1] == 2) {
							$sender->sendMessage(TextFormat::DARK_AQUA . "§f-+ §bFactions Help Page 2/5 §f" . TextFormat::WHITE . "\n§6/f home §f- Teleport to your factions home\n§6/f help <page> §f- Bring up current menu\n§6/f info <faction> §f- View faction info\n§6/f invite <player> §f- Invite a player to your faction\n§6/f kick <player> §f- Kick a player from your faction\n§6/f leader <player> §f- Promote a player to leader of your faction\n§6/f leave §f- Leave your faction");
							return true;
						} 
                        if($args[1] == 3) {
							$sender->sendMessage(TextFormat::GOLD . "§f-+ §bFactions Help Page 3/5 §f" . TextFormat::WHITE . "\n§6/f msg §f- Set message of your faction\n§6/f promote <player> §f- Promote a player to Officer\n§6/f sethome §f- Set home for your faction\n§6/f unclaim §f- Unclaim land claimed by your faction\n§6/f unsethome §f- Remove the current home of your faction");
							return true;
						} 
                        if($args[1] == 4) {
                            $sender->sendMessage(TextFormat::GOLD . "§f-+ §bFactions Help Page 4/5 §f" . TextFormat::WHITE . "\n§6/f power §f- View your faction's power\n§6/f seepower <faction> §f- View faction power\n§6/f ally <faction> §f- Request for an ally\n§6/f unally <faction> §f- Unally a faction\n§6/f aaccept §f- Accept an ally request\n§6/f adeny §f- Deny an ally request\n§6/f pos - Get faction info of your current position");
							return true;
                        } else {
                            $sender->sendMessage(TextFormat::GOLD . "§f-+ §bFactions Staff Help Page 5/5 §f" . TextFormat::WHITE . "\n§6/f funclaim <faction> §f- Unclaim all land of target faction\n§6/f fdisband <faction> §g- Disband a faction\n§6/f faddpower <faction> <number> §f- Add power to a faction");
							return true;
                        }
					}
				}
				if(count($args == 1)) {
					
					/////////////////////////////// CLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to claim"));
							return true;
						}
                        if($this->plugin->prefs->get("OfficersCanClaim")){
                            if(!$this->plugin->isLeader($player) || !$this->plugin->isOfficer($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§6- §cOnly Leaders and Officers can claim"));
							    return true;
						    }
                        } else {
                            if(!$this->plugin->isLeader($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to use this"));
							    return true;
						    }
                        }
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to use this"));
							return true;
						}
                        
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction has already claimed this area."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§6- §cYou need $needed_players more players to claim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction doesn't have enough power to claim"));
							$sender->sendMessage($this->plugin->formatMessage("§6- §c"."$needed_power" . " power is required. Your faction only has $faction_power power."));
                            return true;
                        }
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
                            
							return true;
						}
                        
						$sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
						$sender->sendMessage($this->plugin->formatMessage("Land successfully laimed.", true));
					}
                    if(strtolower($args[0]) == 'pos'){
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        $fac = $this->plugin->factionFromPoint($x,$z);
                        $power = $this->plugin->getFactionPower($fac);
                        if(!$this->plugin->isInPlot($sender)){
                            $sender->sendMessage($this->plugin->formatMessage("This area is unclaimed. Use /f claim to claim", true));
							return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("This plot is claimed by $fac with $power power"));
                    }
                    
                    if(strtolower($args[0]) == 'fdisband') {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /f fdisband <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("Insufficient permissions"));
                            return true;
						}
						$this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
				        $sender->sendMessage($this->plugin->formatMessage("Faction was successfully deleted. All claimed land is now unclaimed.", true));
                    }
                    if(strtolower($args[0]) == 'faddpower') {
                        if(!isset($args[1]) or !isset($args[2])){
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /f faddpower <faction> <number>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("Insufficient permissions"));
                            return true;
						}
                        $this->plugin->addFactionPower($args[1],$args[2]);
				        $sender->sendMessage($this->plugin->formatMessage("Successfully added $args[2] power to $args[1]", true));
                    }
                    if(strtolower($args[0]) == 'overclaim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
                        $faction = $this->plugin->getPlayerFaction($player);
						if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("You need $needed_players more players to overclaim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("Your faction does not have enough power to claim"));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power" . " power is required but your faction only has $faction_power power"));
                            return true;
                        }
						$sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if($this->plugin->prefs->get("EnableOverClaim")){
                            if($this->plugin->isInPlot($sender)){
                                $faction_victim = $this->plugin->factionFromPoint($x,$z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if($this->plugin->inOwnPlot($sender)){
                                    $sender->sendMessage($this->plugin->formatMessage("Your faction has already claimed this land"));
                                    return true;
                                } else {
                                    if($faction_ours_power < $faction_victim_power){
                                        $sender->sendMessage($this->plugin->formatMessage("Your power level is too low to over claim $faction_victim"));
                                        return true;
                                    } else {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
						                $sender->sendMessage($this->plugin->formatMessage("Your faction has successfully overclaimed the land of $faction_victim", true));
                                        return true;
                                    }
                                    
                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("You are not in claimed land"));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Insufficient permissions"));
                            return true;
                        }
                        
					}
                    
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
                        if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("Land successfully unclaimed", true));
					}
					
					/////////////////////////////// MSG ///////////////////////////////
					
					if(strtolower($args[0]) == "msg") {
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("Type your desired message in chat", true));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}
					
					/////////////////////////////// ACCEPT ///////////////////////////////
					
					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any faction"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $invitedTime) <= 60) { //This should be configurable
							$faction = $array["faction"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", strtolower($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("You have joined $faction!", true));
                            $this->plugin->addFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the faction!", true));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					
					/////////////////////////////// DENY ///////////////////////////////
					
					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any faction"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Invite declined!", true));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invite!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					
					/////////////////////////////// DELETE ///////////////////////////////
					
					if(strtolower($args[0]) == "disband") {
						if($this->plugin->isInFaction($player) == true) {
							if($this->plugin->isLeader($player)) {
								$faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
								$sender->sendMessage($this->plugin->formatMessage("Faction has been disbanded and all claimed land is now unclaimed", true));
							} else {
								$sender->sendMessage($this->plugin->formatMessage("You are not leader"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("You are not in a faction"));
						}
					}
					
					/////////////////////////////// LEAVE ///////////////////////////////
					
					if(strtolower($args[0] == "leave")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->plugin->getPlayerFaction($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage($this->plugin->formatMessage("§6- §3You successfully left $faction", true));
                            
                            $this->plugin->subtractFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must delete your faction or give\nleadership to someone else first"));
						}
					}
					
					/////////////////////////////// SETHOME ///////////////////////////////
					
					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to do this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to set home"));
							return true;
						}
                        
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($player));
                        $needed_power = $this->plugin->prefs->get("PowerNeededToSetOrUpdateAHome");
                        if($faction_power < $needed_power){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction doesn't have enough power set a home"));
                            $sender->sendMessage($this->plugin->formatMessage("§6-§c $needed_power power is required to set a home. Your faction has $faction_power power."));
							return true;
                        }
						$factionName = $this->plugin->getPlayerFaction($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("§6- §aFaction home set", true));
					}
					
					/////////////////////////////// UNSETHOME ///////////////////////////////
						
					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to do this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be leader to unset home"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§6- §aHome unset succeed", true));
					}
					
					/////////////////////////////// HOME ///////////////////////////////
						
					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to do this."));
                            return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage($this->plugin->formatMessage("§6- §aTeleported to home.", true));
							return true;
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cFaction home has not been set"));
				        }
				    }
                    
                    /////////////////////////////// POWER ///////////////////////////////
                    if(strtolower($args[0] == "power")) {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to do this"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($sender->getName()));
                        
                        $sender->sendMessage($this->plugin->formatMessage("§6- §3Your faction has§b $faction_power §3power",true));
                    }
                    if(strtolower($args[0] == "seepower")) {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f seepower <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cFaction does not exist"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§6-§e $args[1] §3has $faction_power power.",true));
                    }
                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if(strtolower($args[0] == "ally")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f ally <faction>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be the leader to do this"));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cThe requested faction does not exist"));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction can not ally itself"));
                            return true;
                        }
                        if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction is already allied with $args[1]!"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        if(!($leader instanceof Player)){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §cThe leader of the target faction is offline"));
                            return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
				        $stmt->bindValue(":player", $leader->getName());
				        $stmt->bindValue(":faction", $args[1]);
				        $stmt->bindValue(":requestedby", $sender->getName());
				        $stmt->bindValue(":timestamp", time());
				        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction has requested to ally with $args[1]",true));
                        $leader->sendMessage($this->plugin->formatMessage("§6-§e $fac §3has requested to ally.\n§3Type /f aaccpet to accept or /f adeny to deny.",true));
                        
                    }
                    if(strtolower($args[0] == "unally")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f unally <faction>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be in a faction to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cYou must be the leader to do this"));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6- §cThe requested faction does not exist"));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction cannot unally itself."));
                            return true;
                        }
                        if(!$this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §cYour faction is not allied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);        
						$leader= $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac,$args[1]);
                        $this->plugin->deleteAllies($args[1],$fac);
                        $this->plugin->subtractFactionPower($fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1],$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $sender->sendMessage($this->plugin->formatMessage("§6- §aYour faction $fac is no longer allied with $args[1]!",true));
                        if($leader instanceof Player){
                            $leader->sendMessage($this->plugin->formatMessage("§6-§e $fac §2has unallied with your faction $args[1]",false));
                        }
                        
                        
                    }
                    if(strtolower($args[0] == "funcliam")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§6- §3Usage: /f funclaim <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("Insufficient permissions"));
                            return true;
						}
				        $sender->sendMessage($this->plugin->formatMessage("Land of $args[1] unclaimed"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        
                    }
                    /* pssst. this code is well but the getAllies function sucks as fack.
                    if(strtolower($args[0] == "allies")){
                        if(!isset($args[1])){
                            if(!$this->plugin->isInFaction($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                                return true;
						    }
                            $fac = $this->plugin->getPlayerFaction($player);
                            $all_allies = $this->plugin->getAllAllies($fac);
                            $sender->sendMessage(TextFormat::GREEN . "Allies of : <^$fac^>\n" . $all_allies);
                        } else {
                            if(!$this->plugin->factionExists($args[1])){
							    $sender->sendMessage($this->plugin->formatMessage("Requested faction does not exist."));
                                return true;
                            }
                            $all_allies = $this->plugin->getAllAllies($args[1]);
                            $sender->sendMessage(TextFormat::GREEN . "Allies of : <^$args[1]^>\n" . $all_allies);
                        }
                    }*/
                    if(strtolower($args[0] == "aaccept")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
						}
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Your faction has not been received any ally requests"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->setAllies($requested_fac,$sender_fac);
							$this->plugin->setAllies($sender_fac,$requested_fac);
                            $this->plugin->addFactionPower($sender_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Your faction is now allied with $requested_fac", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has accepted the alliance!", true));
                            
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
                        
                    }
                    if(strtolower($args[0]) == "adeny") {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
						}
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Your faction has not received any ally requests"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $allyTime) <= 60 ) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Your faction has  declined the ally request.", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has declined the alliance!"));
                            
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
					}
                           
                    
					/////////////////////////////// ABOUT ///////////////////////////////
					
					if(strtolower($args[0] == 'about')) {
						$sender->sendMessage(TextFormat::WHITE . "\nThis server is using New FactionsPro v1.7.9 \nYou can start creating your own faction by typing in chat §a/f create <name> \n§fYou can also invite players in your faction by typing in chat §a/f invite <player> \n§fAnd you can also promote your faction members by typing in chat §a/f promote <player>  \n\n§eITS YOUR CHOICE \n§eCREATE, INVITE, JOIN, AND RAID!" . TextFormat::GREEN . "\nOriginal code was written by Tethered_");
						$sender->sendMessage(TextFormat::AQUA . "New FactionsPro v1.7.9 by iChaoticSoldier ");
					}
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Command must be run ingame"));
		}
	}
}
