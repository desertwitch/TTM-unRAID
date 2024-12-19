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

$dwttm_running      = !empty(shell_exec("pgrep -x ttmd 2>/dev/null"));

$dwttm_tmux_available = !empty(shell_exec("type tmux 2>/dev/null"));
$dwttm_tmux_version = htmlspecialchars(trim(shell_exec("tmux -V 2>/dev/null") ?? "n/a"));
$dwttm_tmux_package = htmlspecialchars(trim(shell_exec("find /var/log/packages/ -type f -iname 'tmux*' -printf '%f\n' 2>/dev/null") ?? "n/a"));
$dwttm_tmux_functional = ($dwttm_tmux_available && !empty($dwttm_tmux_version) && $dwttm_tmux_version !== "n/a");
$dwttm_tmux_custom = @file_exists("/boot/config/plugins/dwttm/custom");

?>
