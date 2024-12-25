<?
/* Copyright Derek Macias (parts of code from NUT package)
 * Copyright macester (parts of code from NUT package)
 * Copyright gfjardim (parts of code from NUT package)
 * Copyright SimonF (parts of code from NUT package)
 * Copyright Dan Landon (parts of code from Web GUI)
 * Copyright Bergware International (parts of code from Web GUI)
 * Copyright Lime Technology (any and all other parts of Unraid)
 *
 * Copyright desertwitch (as author and maintainer of this file)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 */
$dwttm_cfg = parse_ini_file("/boot/config/plugins/dwttm/dwttm.cfg");

$dwttm_service = trim(isset($dwttm_cfg['SERVICE']) ? htmlspecialchars($dwttm_cfg['SERVICE']) : 'disable');
$dwttm_service_port = trim(isset($dwttm_cfg['SERVICEPORT']) ? htmlspecialchars($dwttm_cfg['SERVICEPORT']) : '49161');
$dwttm_service_security = trim(isset($dwttm_cfg['SERVICESEC']) ? htmlspecialchars($dwttm_cfg['SERVICESEC']) : 'csrf');
$dwttm_service_route = trim(isset($dwttm_cfg['SERVICEROUTE']) ? htmlspecialchars($dwttm_cfg['SERVICEROUTE']) : 'route');

$dwttm_os_button = trim(isset($dwttm_cfg['OSBUTTON']) ? htmlspecialchars($dwttm_cfg['OSBUTTON']) : 'enable');
$dwttm_os_dashboard = trim(isset($dwttm_cfg['OSDASHBOARD']) ? htmlspecialchars($dwttm_cfg['OSDASHBOARD']) : 'enable');
$dwttm_close_button = trim(isset($dwttm_cfg['CLOSEBUTTON']) ? htmlspecialchars($dwttm_cfg['CLOSEBUTTON']) : 'confirm');
$dwttm_plus_button = trim(isset($dwttm_cfg['PLUSBUTTONSETT']) ? htmlspecialchars($dwttm_cfg['PLUSBUTTONSETT']) : 'quick');
$dwttm_plus_button_pop = trim(isset($dwttm_cfg['PLUSBUTTONPOP']) ? htmlspecialchars($dwttm_cfg['PLUSBUTTONPOP']) : 'quick');
$dwttm_os_configure = trim(isset($dwttm_cfg['OSCONFIGURE']) ? htmlspecialchars($dwttm_cfg['OSCONFIGURE']) : 'disable');
$dwttm_metricsapi = trim(isset($dwttm_cfg['METRICSAPI']) ? htmlspecialchars($dwttm_cfg['METRICSAPI']) : 'enable');

$dwttm_running      = !empty(shell_exec("pgrep -x ttmd 2>/dev/null"));

$dwttm_tmux_available = !empty(shell_exec("type tmux 2>/dev/null"));
$dwttm_tmux_version = htmlspecialchars(trim(shell_exec("tmux -V 2>/dev/null") ?? "n/a"));
$dwttm_tmux_package = htmlspecialchars(trim(shell_exec("find /var/log/packages/ -type f -iname 'tmux*' -printf '%f\n' 2>/dev/null") ?? "n/a"));
$dwttm_tmux_functional = ($dwttm_tmux_available && !empty($dwttm_tmux_version) && $dwttm_tmux_version !== "n/a");
$dwttm_tmux_custom = @file_exists("/boot/config/plugins/dwttm/custom");

?>
