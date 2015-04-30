<?PHP
$plugin = "unassigned.devices";
require_once("plugins/${plugin}/include/lib.php");
require_once ("webGui/include/Helpers.php");

if (isset($_POST['display'])) $display = $_POST['display'];

function render_used_and_free($partition) {
  global $display;
  $o = "";
  if (strlen($partition['target'])) {
    if (!$display['text']) {
      $o .= "<td>".my_scale($partition['used'], $unit)." $unit</td>";
      $o .= "<td>".my_scale($partition['avail'], $unit)." $unit</td>";
    } else {
      $free = round(100*$partition['avail']/$partition['size']);
      $used = 100-$free;
      $o .= "<td><div class='usage-disk'><span style='margin:0;width:{$used}%' class='".usage_color($used,false)."'><span>".my_scale($partition['used'], $unit)." $unit</span></span></div></td>";
      $o .= "<td><div class='usage-disk'><span style='margin:0;width:{$free}%' class='".usage_color($free,true)."'><span>".my_scale($partition['avail'], $unit)." $unit</span></span></div></td>";
    }
  } else {
    $o .= "<td>-</td><td>-</td>";
  }
  return $o;
}

function render_partition($disk, $partition) {
  global $plugin;
  $o = array();
  $fscheck = "/plugins/${plugin}/include/fsck.php?disk={$partition[device]}&fs={$partition[fstype]}&type=ro";
  $icon = "<i class='glyphicon glyphicon-th-large partition'></i>";
  $mounted = is_mounted($partition['device']);
  $fscheck = ( (! $mounted &&  $partition['fstype'] != 'btrfs') || ($mounted && $partition['fstype'] == 'btrfs') ) ? "<a class='exec' onclick='openWindow_fsck(\"{$fscheck}\",\"Check filesystem\",600,900);'>${icon}{$partition[part]}</a>" : "${icon}{$partition[part]}";
  $o[] = "<tr class='$odd toggle-parts toggle-".basename($disk['device'])."' style='__SHOW__' >";
  $o[] = "<td></td>";
  $c = "<td><div>{$fscheck}<i class='glyphicon glyphicon-arrow-right'></i>";
  if ($mounted) {
    $c .= "<a href='/Shares/Browse?dir={$partition[mountpoint]}' target='_blank'>{$partition[mountpoint]}</a>";
  } else {
    $c .= "<form method='POST' action='/plugins/${plugin}/UnassignedDevices.php?action=change_mountpoint&serial={$partition[serial]}&partition={$partition[part]}' target='progressFrame' style='display:inline;margin:0;padding:0;'>";
    $c .= "<span class='text exec'><a>{$partition[mountpoint]}</a></span><input class='input' type='text' name='mountpoint' value='{$partition[mountpoint]}' hidden />";
    $c .= "</form>";
  }
  $o[] = "{$c}</div></td>";
  $o[] = "<td><span style='width:auto;text-align:right;'>".($mounted ? "<button type='button' style='padding:2px 7px 2px 7px;' onclick=\"usb_mount('/usr/local/sbin/unassigned_umount ${partition[device]}');\"><i class='glyphicon glyphicon-export'></i> Unmount</button>" : "<button type='button' style='padding:2px 7px 2px 7px;' onclick=\"usb_mount('/usr/local/sbin/unassigned_mount ${partition[device]}');\"><i class='glyphicon glyphicon-import'></i>  Mount</button>")."</span></td>";
  $o[] = "<td>-</td>";
  $o[] = "<td >".$partition['fstype']."</td>";
  $o[] = "<td><span>".my_scale($partition['size'], $unit)." $unit</span></td>";
  $o[] = render_used_and_free($partition);
  $o[] = "<td>".(strlen($partition['target']) ? shell_exec("lsof '${partition[target]}' 2>/dev/null|grep -c -v COMMAND") : "-")."</td>";
  $o[] = "<td>-</td>";
  $o[] = "<td><input type='checkbox' class='toggle_share' info='".htmlentities(json_encode($partition))."' ".(($partition['shared']) ? 'checked':'')."></td>";
  $o[] = "<td><a href='/Main/EditScript?s=".urlencode($partition['serial'])."&l=".urlencode(basename($partition['mountpoint']))."&p=".urlencode($partition['part'])."'><img src='/webGui/images/default.png' style='cursor:pointer;width:16px;".( (get_config($partition['serial'],"command.{$partition[part]}")) ? "":"opacity: 0.4;" )."'></a></td>";
  $o[] = "<tr>";
  return $o;
}

switch ($_POST['action']) {
  case 'get_content':
    $disks = get_all_disks_info();
    echo "<table class='usb_disks custom_head'><thead><tr><td>Device</td><td>Identification</td><td></td><td>Temp</td><td>FS</td><td>Size</td><td>Used</td><td>Free</td><td>Open files</td><td>Auto mount</td><td>Share</td><td>Script</td></tr></thead>";
    echo "<tbody>";
    if ( count($disks) ) {
      $odd="odd";
      foreach ($disks as $disk) {
        $disk_mounted = false;
        foreach ($disk['partitions'] as $p) if (is_mounted($p['device'])) $disk_mounted = TRUE;
        $temp = my_temp($disk['temperature']);
        $p = (count($disk['partitions']) == 1) ? render_partition($disk, $disk['partitions'][0]) : FALSE;
        echo "<tr class='$odd'>";
        printf( "<td><img src='/webGui/images/%s'> %s</td>", ( is_disk_running($disk['device']) ? "green-on.png":"green-blink.png" ), basename($disk['device']) );
        echo "<td><span class='exec toggle-hdd' hdd='".basename($disk['device'])."'><i class='glyphicon glyphicon-hdd hdd'></i>".($p?"<span style='margin:4px;'></span>":"<i class='glyphicon glyphicon-plus-sign glyphicon-append'></i>").$disk['partitions'][0]['serial']."</td>";
        echo "<td><span style='width:auto;text-align:right;'>".($disk_mounted ? "<button type='button' style='padding:2px 7px 2px 7px;' onclick=\"usb_mount('/usr/local/sbin/unassigned_umount {$disk[device]}');\"><i class='glyphicon glyphicon-export'></i> Unmount</button>" : "<button type='button' style='padding:2px 7px 2px 7px;' onclick=\"usb_mount('/usr/local/sbin/unassigned_mount {$disk[device]}');\"><i class='glyphicon glyphicon-import'></i>  Mount</button>")."</span></td>";
        echo "<td>{$temp}</td>";
        echo ($p)?$p[5]:"<td>-</td>";
        echo ($p)?$p[6]:"<td>".my_scale($disk['size'],$unit)." {$unit}</td>";
        echo ($p)?$p[7]:"<td>-</td>";
        echo ($p)?$p[8]:"<td>-</td><td>-</td>";
        echo "<td><input type='checkbox' class='automount' serial='".$disk['partitions'][0]['serial']."' ".(($disk['partitions'][0]['automount']) ? 'checked':'')."></td>";
        echo ($p)?$p[10]:"<td>-</td>";
        echo ($p)?$p[11]:"<td>-</td>";
        echo "</tr>";
        if (! $p || $p) {
          foreach ($disk['partitions'] as $partition) {
            foreach (render_partition($disk, $partition) as $l) echo str_replace("__SHOW__", (count($disk['partitions']) >1 ? "display:none;":"display:none;" ), $l );
          }
        }
        $odd = ($odd == "odd") ? "even" : "odd";
      }
    } else {
      echo "<tr><td colspan='12' style='text-align:center;font-weight:bold;'>No unassigned disks available.</td></tr>";
    }
    echo "</tbody></table><div style='min-height:20px;'></div>";

    $config_file = $GLOBALS["paths"]["config_file"];
    $config = is_file($config_file) ? @parse_ini_file($config_file, true) : array();
    $disks_serials = array();
    foreach ($disks as $disk) $disks_serials[] = $disk['partitions'][0]['serial'];
    $ct = "";
    foreach ($config as $serial => $value) {
      if (! preg_grep("#${serial}#", $disks_serials)){
        $ct .= "<tr><td><img src='/webGui/images/green-blink.png'> missing</td><td>$serial</td><td><input type='checkbox' class='automount' serial='${serial}' ".( is_automount($serial) ? 'checked':'' )."></td><td colspan='7'><a style='cursor:pointer;' onclick='remove_disk_config(\"${serial}\")'>Remove</a></td></tr>";
      }
    }
    if (strlen($ct)) echo "<table class='usb_absent custom_head'><thead><tr><td>Device</td><td>Serial Number</td><td>Auto mount</td><td colspan='7'>Remove config</td></tr></thead><tbody>${ct}</tbody></table>";

    echo 
    '<script type="text/javascript">
    $(".automount").each(function(){var checked = $(this).is(":checked");$(this).switchButton({labels_placement: "right", checked:checked});});
    $(".automount").change(function(){$.post(URL,{action:"automount",serial:$(this).attr("serial"),status:$(this).is(":checked")},function(data){$(this).prop("checked",data.automount);},"json");});
    
    $(".toggle_share").each(function(){var checked = $(this).is(":checked");$(this).switchButton({labels_placement: "right", checked:checked});});
    $(".toggle_share").change(function(){$.post(URL,{action:"toggle_share",info:$(this).attr("info"),status:$(this).is(":checked")},function(data){$(this).prop("checked",data.result);},"json");});
    $(".text").click(showInput);$(".input").blur(hideInput);
    $(function(){
      $(".toggle-hdd").click(function(e) {
        $(this).disableSelection();disk = $(this).attr("hdd");el = $(this);
        $(".toggle-"+disk).slideToggle(0,function(){
          if ( $("tr.toggle-"+disk+":first").is(":visible") ){
            el.find(".glyphicon-append").addClass("glyphicon-minus-sign").removeClass("glyphicon-plus-sign");
          } else {
            el.find(".glyphicon-append").removeClass("glyphicon-minus-sign").addClass("glyphicon-plus-sign");
          }
        });
      });
    });
    </script>';
    break;
  case 'detect':
    if (is_file("/var/state/${plugin}")) {
      echo json_encode(array("reload" => true));
    } else {
      echo json_encode(array("reload" => false));
    }
    break;
  case 'remove_hook':
    @unlink("/var/state/${plugin}");
    break;
  case 'automount':
    $serial = urldecode(($_POST['serial']));
    $status = urldecode(($_POST['status']));
    echo json_encode(array( 'automount' => toggle_automount($serial, $status) ));
  break;
  case 'get_command':
    $serial = urldecode(($_POST['serial']));
    $part   = urldecode(($_POST['part']));
    echo json_encode(array( 'command' => get_config($serial, "command.{$part}")));
  break;
  case 'set_command':
    $serial = urldecode(($_POST['serial']));
    $part = urldecode(($_POST['part']));
    $cmd = urldecode(($_POST['command']));
    echo json_encode(array( 'result' => set_config($serial, "command.{$part}", $cmd)));
  break;
  case 'remove_config':
    $serial = urldecode(($_POST['serial']));
    echo json_encode(array( 'result' => remove_config_disk($serial)));
  break;
  case 'toggle_share':
    $info = json_decode(html_entity_decode($_POST['info']), true);
    $status = urldecode(($_POST['status']));
    $result = toggle_share($info['serial'], $info['part'],$status);
    echo json_encode(array( 'result' => $result));
    if ($result && strlen($info['target'])) {
      add_smb_share($info['mountpoint'], $info['label']);
    } else {
      rm_smb_share($info['mountpoint'], $info['label']);
    }
  break;

}
switch ($_GET['action']) {
  case 'change_mountpoint':
    $serial = urldecode($_GET['serial']);
    $partition = urldecode($_GET['partition']);
    $mountpoint = urldecode($_POST['mountpoint']);
    set_config($serial, "mountpoint.${partition}", $mountpoint);
    require_once("/usr/local/emhttp/update.htm");
    break;
}
?>
