###############################################################################
# Overrides locais da RepeaterLogic - Projeto Repetidor BDNet
###############################################################################

namespace eval RepeaterLogic {

proc bdnet_ident {} {
  set audio "/var/lib/repeater/id.wav"

  puts "BDNET: bdnet_ident chamado"

  if {[file exists $audio]} {
    puts "BDNET: tocando arquivo $audio"
    playSilence 300
    playFile $audio
    playSilence 300
  } else {
    global mycall
    spellWord $mycall
    playMsg "Core" "repeater"
    playSilence 250
  }
}

proc manual_identification {} {
  bdnet_ident
}

proc repeater_up {reason} {
  variable repeater_is_up
  set repeater_is_up 1
}

proc repeater_down {reason} {
  variable repeater_is_up
  set repeater_is_up 0

  if {$reason == "SQL_FLAP_SUP"} {
    playSilence 500
    playMsg "Core" "interference"
    playSilence 500
    return
  }
}

proc repeater_idle {} {
  bdnet_time_announce
}


proc bdnet_ptt_set {value} {
  set ptt "/sys/class/gpio/gpio534/value"
  if {[file exists $ptt]} {
    catch {exec sh -c "echo $value > $ptt"}
  }
}

proc bdnet_time_announce {} {
  set flag "/tmp/repeater_time_announce"
  set duration_file "/tmp/repeater_time_announce.duration"
  if {![file exists $flag]} {
    return 0
  }
  variable repeater_is_up
  if {[info exists repeater_is_up] && $repeater_is_up == 1} {
    return 0
  }
  set wav [string trim [exec cat $flag]]
  set dur_ms 10000
  if {[file exists $duration_file]} {
    if {![catch {set dur_txt [string trim [exec cat $duration_file]]}]} {
      if {[string is double -strict $dur_txt]} {
        set dur_ms [expr {int(($dur_txt + 1.2) * 1000)}]
      }
    }
  }
  if {[file exists $wav]} {
    puts "BDNET: falando hora certa $wav por $dur_ms ms"
    if {[catch {
      playSilence 500
      playFile $wav
      playSilence 300
    } err]} {
      puts "BDNET: erro ao falar hora certa: $err"
    }
    file delete -force $flag
    file delete -force $duration_file
    return 1
  }
  return 0
}


proc bdnet_dtmf_cmd_received {cmd} {
  set clean [string toupper [string map {"#" "" " " ""} $cmd]]
  if {$clean == ""} {
    return 0
  }
  if {[catch {exec /usr/local/bin/repeater-dtmf-action $clean} result]} {
    puts "BDNET DTMF: erro ao executar comando $clean: $result"
    return 0
  }
  if {[string trim $result] != ""} {
    puts "BDNET DTMF: $result"
    return 1
  }
  return 0
}

proc dtmf_cmd_received {cmd} {
  if {[bdnet_dtmf_cmd_received $cmd]} {
    return 1
  }
  if {[llength [info commands Logic::dtmf_cmd_received]]} {
    return [Logic::dtmf_cmd_received $cmd]
  }
  return 0
}

proc checkPeriodicIdentify {} {
  if {![bdnet_time_announce]} {
    Logic::checkPeriodicIdentify
  }
}

proc get_config_value {key default} {
  set cfg "/etc/repeater/config.json"
  if {[file exists $cfg]} {
    set cmd "python3 -c \"import json; d=json.load(open('$cfg')); print(d.get('$key','$default'))\""
    set value [exec sh -c $cmd]
    return $value
  }
  return $default
}


proc bdnet_tot_alarm {} {
  set alarm "/tmp/repeater_tot_alarm"
  if {[file exists $alarm]} {
    puts "BDNET: alerta TOT"
    playTone 400 150 100
    playSilence 80
    playTone 400 150 100
    playSilence 80
    playTone 400 300 100
    file delete -force $alarm
  }
}


proc send_rgr_sound {} {
  bdnet_tot_alarm
  set bip [get_config_value "bip_cortesia" "simples"]
  set tone_level 220
  puts "BDNET: bip de cortesia $bip"

  if {$bip == "motorola"} {
    playTone 900 60 $tone_level
    playSilence 40
    playTone 1200 80 $tone_level
  } elseif {$bip == "kenwood"} {
    playTone 1200 120 $tone_level
  } elseif {$bip == "icom"} {
    playTone 700 100 $tone_level
  } elseif {$bip == "yaesu"} {
    playTone 800 50 $tone_level
    playSilence 40
    playTone 1400 50 $tone_level
  } elseif {$bip == "hytera"} {
    playTone 1000 50 $tone_level
    playSilence 30
    playTone 1600 60 $tone_level
  } elseif {$bip == "harris"} {
    playTone 500 120 $tone_level
  } elseif {$bip == "tait"} {
    playTone 1100 60 $tone_level
    playSilence 50
    playTone 900 60 $tone_level
  } elseif {$bip == "sepura"} {
    playTone 400 100 $tone_level
  } else {
    playTone 1000 120 $tone_level
  }
}


proc gpio_write {gpio value} {
  set path "/sys/class/gpio/gpio$gpio/value"
  if {[file exists $path]} {
    exec sh -c "echo $value > $path"
  }
}

proc bdnet_cooler_on {} {
  set gpio [get_config_value "gpio_cooler" "18"]
  puts "BDNET: cooler ON gpio$gpio"
  gpio_write $gpio 1
}

proc bdnet_cooler_off {} {
  set gpio [get_config_value "gpio_cooler" "18"]
  set tempo [get_config_value "cooler_tempo" "30"]
  puts "BDNET: cooler OFF em ${tempo}s gpio$gpio"
  exec sh -c "sleep $tempo; echo 0 > /sys/class/gpio/gpio$gpio/value" &
}

proc transmit {is_on} {
  Logic::transmit $is_on
  if {$is_on} {
    bdnet_cooler_on
  } else {
    bdnet_cooler_off
  }
}


}
