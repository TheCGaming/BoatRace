<?php
namespace Sandertv\BoatRacePE;

use BukkitPE\plugin\PluginBase;
use BukkitPE\utils\TextFormat as Color;
use BukkitPE\utils\Config;
use BukkitPE\event\Listener;
use BukkitPE\event\entity\EntityDamageEvent;
use BukkitPE\event\entity\EntityDamageByEntityEvent;
use BukkitPE\event\player\PlayerDeathEvent;
use BukkitPE\event\player\PlayerInteractEvent;
use BukkitPE\math\Vector3;
use BukkitPE\level\Position;
use BukkitPE\command\Command, CommandSender;
use BukkitPE\Player;
use BukkitPE\block\Block;
use BukkitPE\item\Item;
use BukkitPE\block\WallSign, PostSign;
use BukkitPE\scheduler\ServerScheduler;

class BoatRacePE extends PluginBase implements Listener
{

  // Colors
  public $reds = [];
  public $blues = [];
  public $gameStarted = false;
  public $yml;

  public function onEnable()
{
    // Initializing config files
    $this->saveResource("config.yml");
    $yml = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    $this->yml = $yml->getAll();
    $this->getLogger()->debug("Config files have been saved!");
        
   $level = $this->yml["sign_world"];
    
  if(!$this->getServer()->isLevelGenerated($level)){
    $this->getLogger()->error("The level you used on the config ( " . $level . " ) doesn't exist! stopping plugin...");
    $this->getServer()->getPluginManager()->disablePlugin($this->getServer()->getPluginManager()->getPlugin("BoatRacePE"));
    }
    
    if(!$this->getServer()->isLevelLoaded($level)){
      $this->getServer()->loadLevel($level);
    }
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new Tasks\SignUpdaterTask($this), 15);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getLogger()->info(Color::BOLD . Color::AQUA . "BoatRacePE " . Color::GREEN . "Enabled" . Color::RED . "!");
    }
    public function isFriend($p1, $p2)
    {
        if ($this->getTeam($p1) === $this->getTeam($p2) && $this->getTeam($p1) !== false) {
            return true;
        } else {
            return false;
        }
    }
    // isFriend
    public function getTeam($p)
    {
        if (in_array($p, $this->reds)) {
            return "red";
        } elseif (in_array($p, $this->blues)) {
            return "blue";
        } else {
            return false;
        }
    }
    public function setTeam($p, $team)
    {
        if (strtolower($team) === "red") {
            if (count($this->reds) < 2) {
                if ($this->getTeam($p) === "blue") {
                    unset($this->blues{
                    array_search(
                                $p
                                , 
                                $this->blues)
                                
                    });
                }
                array_push($this->reds, $p);
                $this->getServer()->getPlayer($p)->setNameTag("§c§l" . $p);
                $this->getServer()->getPlayer($p)->teleport(new Vector3($this->yml["waiting_x"], $this->yml["waiting_y"], $this->yml["waiting_z"]));
                return true;
            } elseif (count($this->blues) < 2) {
                $this->setTeam($p, "blue");
            } else {
                return false;
            }
        } elseif (strtolower($team) === "blue") {
            if (count($this->blues) < 2) {
                if ($this->getTeam($p) === "red") {
                    unset($this->reds{
                    array_search(
                                $p
                                 , 
                                 $this->reds)
                            
                    }
                    );
                }
                array_push($this->blues, $p);
                $this->getServer()->getPlayer($p)->setNameTag("§b§l" . $p);
                $this->getServer()->getPlayer($p)->teleport(new Vector3($this->yml["waiting_x"], $this->yml["waiting_y"], $this->yml["waiting_z"]));
                return true;
            } elseif (count($this->reds) < 2) {
                $this->setTeam($p, "red");
            } else {
                return false;
            }
        }
    }
    public function removeFromTeam($p, $team)
    {
        if (strtolower($team) == "red") {
            unset($this->reds{array_search(
                $p
                , 
                $this->reds)
                
            }
            );
            return true;
        } elseif (strtolower($team) == "blue") {
            unset($this->blues{array_search(
            $p
            ,
            $this->blues)
                
            }
            );
            return true;
        }
    }
    public function onInteract(PlayerInteractEvent $event)
    {
        $p = $event->getPlayer();
        $teams = array("red", "blue");
        if ($event->getBlock()->getX() === $this->yml["sign_join_x"] && $event->getBlock()->getY() === $this->yml["sign_join_y"] && $event->getBlock()->getZ() === $this->yml["sign_join_z"]) {
            if (count($this->blues) !== 1 and count($this->reds) !== 1) {
                $this->setTeam($p->getName(), $teams{
                    array_rand(
                    $teams, 2)
                });
                $s = new GameManager();
                $s->run();
            } else {
                $p->sendMessage($this->yml["Boat race is full!"]);
            }
        }
    }
    public function onEntityDamage(EntityDamageEvent $event)
    {
        if ($event instanceof EntityDamageByEntityEvent) {
            if ($event->getEntity() instanceof Player) {
                if ($this->isFriend($event->getDamager()->getName(), $event->getEntity()->getName()) && $this->gameStarted == true) {
                    $event->setCancelled(true);
                    $event->getDamager()->sendMessage(str_replace("{player}", $event->getPlayer()->getName(), $this->yml["hit_same_team_message"]));
                }
                if ($this->isFriend($event->getDamager()->getName(), $event->getEntity()->getName())) {
                    $event->setCancelled(true);
                }
            }
        }
    }
    public function onDeath(PlayerDeathEvent $event)
    {
        $a = array("WON" <= array());
        
        if ($this->getTeam($event->getEntity()->getName()) == "red" && $this->gameStarted == true) {
            $this->removeFromTeam($event->getEntity()->getName(), "red");
            $event->getEntity()->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn());
        } elseif ($this->getTeam($event->getEntity()->getName()) == "blue" && $this->gameStarted == true) {
            $this->removeFromTeam($event->getEntity()->getName(), "blue");
            $event->getEntity()->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn());
        }
        foreach ($this->blues as $b) {
            foreach ($this->reds as $r) {
                if (count($this->reds) == 0 && $this->gameStarted == true) {
                    $a{
                        "WON"
                        
                    } = "BLUE";
                }
                if (count($this->blues) == 0 && $this->gameStarted == true) {
                    $a{
                        "WON"
                        
                    } = "RED";
                }
                if($a[0] == "BLUE"){
                     $this->getServer()->getPlayer($b)->getInventory()->clearAll();
                    $this->removeFromTeam($b, "blue");
                    $this->getServer()->getPlayer($b)->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn());
                    $this->gameStarted = false;
                    $this->getServer()->broadcastMessage("Blue Side won the Boat Race!");
                    $this->getServer()->removeEntities();
                $a{
                    "WON"
                    
                } = "False";
                
                }else{
                    return FALSE;
                }
                if ($a[0] == "RED"){
                    $this->getServer()->getPlayer($r)->getInventory()->clearAll();
                    $this->removeFromTeam($r, "red");
                    $this->getServer()->getPlayer($r)->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn());
                    $this->gameStarted = false;
                    $this->getServer()->broadcastMessage("Red Side won the Boat Race!");
                    $this->getServer()->removeEntities();
                $a{
                    "WON"
                    
                } = "False";
                }else{
                    return FALSE;
                }
                if($a[0] == "False"){
                    return;
                }
            }
        }
    }
}//class
