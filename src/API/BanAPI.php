<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

class BanAPI{
	private $server;
	private $whitelist;
	private $banned;
	private $ops;
	private $bannedIPs;
	function __construct(PocketMinecraftServer $server){
		$this->server = $server;
	}
	
	public function init(){
		console("[INFO] Loading authentication lists...");
		$this->whitelist = new Config(FILE_PATH."white-list.txt", CONFIG_LIST);
		$this->bannedIPs = new Config(FILE_PATH."banned-ips.txt", CONFIG_LIST);
		$this->banned = new Config(FILE_PATH."banned.txt", CONFIG_LIST);
		$this->ops = new Config(FILE_PATH."ops.txt", CONFIG_LIST);
		$this->server->api->console->register("banip", "Manages IP Banning", array($this, "commandHandler"));
		$this->server->api->console->register("ban", "Manages Bannning", array($this, "commandHandler"));
		$this->server->api->console->register("kick", "Kicks a player", array($this, "commandHandler"));
		$this->server->api->console->register("whitelist", "Manages White-listing", array($this, "commandHandler"));
		$this->server->api->console->register("op", "Ops a player", array($this, "commandHandler"));
		$this->server->api->console->register("deop", "Deops a player", array($this, "commandHandler"));
		$this->server->addHandler("console.command", array($this, "opCheck"), 1);
	}
	
	public function isOp($username){
		if($this->server->api->dhandle("api.op.check", $username) === false){
			return true;
		}elseif($this->op->exists($username)){
			return true;
		}
		return false;	
	}
	
	public function opCheck($data, $event){
		if($data["issuer"] instanceof Player){
			if($this->isOp($data["issuer"]->username)){
				return true;
			}
		}elseif($data["issuer"] === "console"){
			return true;
		}
		return false;
	}
	
	public function commandHandler($cmd, $params, $issuer){
		$output = "";
		switch($cmd){
			case "op":
				$user = trim(implode(" ", $params));
				if($user == ""){
					break;
				}
				$this->ops->set($user);
				$this->ops->save();
				$output .= $user." is now op\n";
				break;
			case "deop":
				$user = trim(implode(" ", $params));
				if($user == ""){
					break;
				}
				$this->ops->remove($user);
				$this->ops->save();
				$output .= $user." is not longer op\n";
				break;
			case "kick":
				if(!isset($params[0])){
					$output .= "Usage: /kick <playername> [reason]\n";
				}else{
					$name = array_shift($params);
					$player = $this->server->api->player->get($name);
					if($player === false){
						$output .= "Player \"".$name."\" does not exist\n";
					}else{
						$reason = implode(" ", $params);
						$reason = $reason == "" ? "No reason":$reason;
						$player->close("You have been kicked: ".$reason);
						if($issuer instanceof Player){
							$this->server->api->chat->broadcast($player->username." has been kicked by ".$issuer->username.": $reason\n");
						}else{
							$this->server->api->chat->broadcast($player->username." has been kicked: $reason\n");
						}
					}
				}
				break;
			case "whitelist":
				$p = strtolower(array_shift($params));
				switch($p){
					case "remove":
						$user = trim(implode(" ", $params));
						$this->whitelist->remove($user);
						$this->whitelist->save();
						$output .= "Player \"$user\" removed from white-list\n";
						break;
					case "add":
						$user = trim(implode(" ", $params));
						$this->whitelist->set($user);
						$this->whitelist->save();
						$output .= "Player \"$user\" added to white-list\n";
						break;
					case "reload":
						$this->whitelist = $this->load(FILE_PATH."white-list.txt", CONFIG_LIST);
						break;
					case "list":
						$output .= "White-list: ".implode(", ", $this->whitelist->getAll(true))."\n";
						break;
					case "on":
					case "true":
					case "1":
						$output .= "White-list turned on\n";
						$this->server->api->setProperty("white-list", true);
						break;
					case "off":
					case "false":
					case "0":
						$output .= "White-list turned off\n";
						$this->server->api->setProperty("white-list", false);
						break;
					default:
						$output .= "Usage: /whitelist <on | off | add | remove | reload | list> [username]\n";
						break;
				}
				break;
			case "banip":
				$p = strtolower(array_shift($params));
				switch($p){
					case "pardon":
					case "remove":
						$ip = trim(implode($params));
						$this->bannedIPs->remove($ip);
						$this->bannedIPs->save();
						$output .= "IP \"$ip\" removed from ban list\n";
						break;
					case "add":
					case "ban":
						$ip = trim(implode($params));
						$this->bannedIPs->set($ip);
						$this->bannedIPs->save();
						$output .= "IP \"$ip\" added to ban list\n";
						break;
					case "reload":
						$this->bannedIPs = new Config(FILE_PATH."banned-ips.txt", CONFIG_LIST);
						break;
					case "list":
						$output .= "IP ban list: ".implode(", ", $this->bannedIPs->getAll(true))."\n";
						break;
					default:
						$output .= "Usage: /banip <add | remove | list | reload> [IP]\n";
						break;
				}
				break;
			case "ban":
				$p = strtolower(array_shift($params));
				switch($p){
					case "pardon":
					case "remove":
						$user = trim(implode($params));
						$this->banned->remove($user);
						$this->banned->save();
						$output .= "Player \"$user\" removed from ban list\n";
						break;
					case "add":
					case "ban":
						$user = trim(implode($params));
						$this->banned->set($user);
						$this->banned->save();
						$player = $this->server->api->player->get($user);
						if($player !== false){
							$player->close("You have been banned");
						}
						$this->server->api->chat->broadcast("$user has been banned");
						if($issuer instanceof Player){
							$this->server->api->chat->broadcast($user." has been banned by ".$issuer->username."\n");
						}else{
							$this->server->api->chat->broadcast($user." has been banned\n");
						}
						$output .= "Player \"$user\" added to ban list\n";
						break;
					case "reload":
						$this->banned = new Config(FILE_PATH."banned.txt", CONFIG_LIST);
						break;
					case "list":
						$output .= "Ban list: ".implode(", ", $this->banned->getAll(true))."\n";
						break;
					default:
						$output .= "Usage: /ban <add | remove | list | reload> [player]\n";
						break;
				}
				break;
		}
		return $output;
	}
	
	public function ban($username){
		$this->commandHandler("ban", array("add", $username));
	}
	
	public function pardon($username){
		$this->commandHandler("ban", array("pardon", $username));
	}
	
	public function banIP($ip){
		$this->commandHandler("banip", array("add", $ip));
	}
	
	public function pardonIP($ip){
		$this->commandHandler("banip", array("pardon", $ip));
	}
	
	public function kick($username, $reason){
		$this->commandHandler("kick", array($username, $reason));
	}
	
	public function reload(){
		$this->commandHandler("ban", array("reload"));
		$this->commandHandler("banip", array("reload"));
		$this->commandHandler("whitelist", array("reload"));
	}
	
	public function isIPBanned($ip){
		if($this->server->api->dhandle("api.ban.ip.check", $ip) === false){
			return true;
		}elseif($this->bannedIPs->exists($ip)){
			return true;
		}
		return false;
	}
	
	public function isBanned($username){
		if($this->server->api->dhandle("api.ban.check", $username) === false){
			return true;
		}elseif($this->banned->exists($username)){
			return true;
		}
		return false;	
	}
	
	public function inWhitelist($username){
		if($this->server->api->dhandle("api.ban.whitelist.check", $ip) === false){
			return true;
		}elseif($this->whitelist->exists($username)){
			return true;
		}
		return false;	
	}
}