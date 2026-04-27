<?php
// Patched copy of Issabel PBX admin content loader.
// Fix: guard menu array initialization to avoid:
// "Cannot use string offset as an array" on line 715 in legacy file.

require_once "libs/paloSantoACL.class.php";

function getContent(&$smarty, $iss_module_name, $withList)
{
    global $fc_save, $arrConf, $arrLang, $tabindex;
    global $display, $type, $online;

    require_once "libs/misc.lib.php";
    $lang = get_language();
    $base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

    load_language_module($iss_module_name);

    $dieafterquiet = false;
    $api_modules = array();

    $it = new RecursiveDirectoryIterator("admin/modules/");
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if (preg_match("/guimodule\.php$/ui", $file)) {
            include_once $file;
        }
    }

    foreach ($api_modules as $key => $endpoint) {
        if (!file_exists('/var/www/html/newgui/' . $endpoint . '.html')) {
            unset($api_modules[$key]);
        }
    }

    $arrLangIssabelPBX = array(
        "en" => "en_US", "bg" => "bg_BG", "cn" => "zh_CN", "de" => "de_DE", "es" => "es_ES",
        "fr" => "fr_FR", "he" => "he_IL", "hu" => "hu_HU", "it" => "it_IT",
        "pt" => "pt_PT", "ru" => "ru_RU", "sv" => "sv_SE", "br" => "pt_BR"
    );

    $langIssabelPBX = isset($arrLangIssabelPBX[$lang]) ? $arrLangIssabelPBX[$lang] : "en_US";
    setcookie("lang", $langIssabelPBX);
    $local_templates_dir = "$base_dir/modules/$iss_module_name/themes/default";

    $vars = array(
        'action' => null,
        'confirm_email' => '',
        'confirm_password' => '',
        'display' => '',
        'extdisplay' => null,
        'email_address' => '',
        'fw_popover' => '',
        'fw_popover_process' => '',
        'logout' => false,
        'password' => '',
        'quietmode' => '',
        'restrictmods' => false,
        'skip' => 0,
        'skip_astman' => false,
        'type' => '',
        'username' => '',
    );

    if (!isset($_REQUEST['display'])) {
        $_REQUEST['display'] = 'extensions';
        $_REQUEST['type'] = 'setup';
        $_GET['display'] = 'extensions';
        $_GET['type'] = 'setup';
    }

    foreach ($vars as $k => $v) {
        $config_vars[$k] = $$k = isset($_REQUEST[$k]) ? $_REQUEST[$k] : $v;
        switch ($k) {
            case 'extdisplay':
                $extdisplay = (isset($extdisplay) && $extdisplay !== false)
                    ? htmlspecialchars($extdisplay, ENT_QUOTES)
                    : false;
                $_REQUEST['extdisplay'] = $extdisplay;
                break;
            case 'restrictmods':
                $restrict_mods = $restrictmods ? array_flip(explode('/', $restrictmods)) : false;
                break;
            case 'skip_astman':
                $bootstrap_settings['skip_astman'] = $skip_astman;
                break;
        }
    }

    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Content-Type: text/html; charset=utf-8');

    global $amp_conf, $db, $no_auth, $currentcomponent, $CC, $dirname, $bootstrap_settings;
    global $astman, $extmap, $module_name, $module_page, $reload_needed, $active_modules;
    global $remove_rnav, $js_content, $use_popover_css, $popover_mode, $quietmode;
    global $recordings_save_path, $path_to_dir, $itemid;
    $return_HTML = "";

    if (isset($_REQUEST['handler'])) {
        $restrict_mods = true;
        switch ($_REQUEST['handler']) {
            case 'api':
                $restrict_mods = false;
                break;
            case 'reload':
                break;
            default:
                $bootstrap_settings['skip_astman'] = true;
                break;
        }
    }

    $bootstrap_settings['issabelpbx_auth'] = false;

    if (is_file('/etc/issabelpbx.conf')) {
        if (!@include_once(getenv('ISSABELPBX_CONF') ? getenv('ISSABELPBX_CONF') : '/etc/issabelpbx.conf')) {
            include_once('/etc/asterisk/issabelpbx.conf');
        }
    } else {
        if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
            include_once('/etc/asterisk/freepbx.conf');
        }
    }

    $username = "admin";
    $_SESSION['AMP_user'] = new ampuser($username);
    set_language();

    if (!isset($no_auth) && $action != '' && $amp_conf['CHECKREFERER']) {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            $refererok = (trim($referer['host']) == trim($_SERVER['SERVER_NAME'])) ? true : false;
        } else {
            $refererok = false;
        }
        if (!$refererok) {
            $display = 'badrefer';
        }
    }
    if (isset($no_auth) && empty($display)) {
        $display = 'noauth';
    }
    if (!in_array($display, array('noauth', 'badrefer')) && isset($_REQUEST['handler'])) {
        $module = isset($_REQUEST['module']) ? $_REQUEST['module'] : '';
        $file = isset($_REQUEST['file']) ? $_REQUEST['file'] : '';
        fileRequestHandler($_REQUEST['handler'], $module, $file);
        exit();
    }

    if (!$quietmode) {
        module_run_notification_checks();
    }

    $allmodules = module_getinfo(false, false, true);
    $ipbx_menu = array();
    $cur_menuitem = null;

    if (is_array($active_modules)) {
        foreach ($active_modules as $key => $module) {
            if (isset($module['items']) && is_array($module['items'])) {
                foreach ($module['items'] as $itemKey => $item) {
                    $needs_perms = !isset($item['access']) || strtolower($item['access']) != 'all' ? true : false;
                    $admin_auth = isset($_SESSION["AMP_user"]) && is_object($_SESSION["AMP_user"]);
                    $has_perms = $admin_auth && $_SESSION["AMP_user"]->checkSection($itemKey);
                    $needs_auth = isset($item['requires_auth']) && strtolower($item['requires_auth']) == 'false' ? false : true;

                    if ($needs_auth && (!$admin_auth || ($needs_perms && !$has_perms))) {
                        if ($display == $itemKey) {
                            $display = $admin_auth ? 'noaccess' : 'noauth';
                        }
                        continue;
                    }

                    if (!isset($item['display'])) {
                        $item['display'] = $itemKey;
                    }
                    $item['module'] =& $active_modules[$key];
                    $ipbx_menu[$itemKey] = $item;

                    if (($item['display'] == 'index') && ($display == '')) {
                        $display = 'index';
                    }
                    if ($display == $item['display']) {
                        $cur_menuitem =& $ipbx_menu[$itemKey];
                    }
                }
            }
        }
    }

    if ($cur_menuitem === null && !in_array($display, array('noauth', 'badrefer', 'noaccess', ''))) {
        $display = 'noaccess';
    }

    if (!$quietmode && is_array($active_modules)) {
        foreach ($active_modules as $key => $module) {
            modgettext::push_textdomain($module['rawname']);
            if (isset($module['items']) && is_array($module['items'])) {
                foreach ($module['items'] as $itemKey => $itemName) {
                    $initfuncname = $key . '_' . $itemKey . '_configpageinit';
                    if (function_exists($initfuncname)) {
                        $configpageinits[] = $initfuncname;
                    }
                }
            }
            $initfuncname = $key . '_configpageinit';
            if (function_exists($initfuncname)) {
                $configpageinits[] = $initfuncname;
            }
            modgettext::pop_textdomain();
        }
    }

    if (!$quietmode && isset($ipbx_menu["extensions"])) {
        if (isset($amp_conf["AMPEXTENSIONS"]) && ($amp_conf["AMPEXTENSIONS"] == "deviceanduser")) {
            unset($ipbx_menu["extensions"]);
        } else {
            unset($ipbx_menu["devices"]);
            unset($ipbx_menu["users"]);
        }
    }

    ob_start();

    if (!in_array($display, array('', 'badrefer')) && isset($configpageinits) && is_array($configpageinits)) {
        $CC = $currentcomponent = new component($display, $type);
        foreach ($configpageinits as $func) {
            $func($display);
        }
        $currentcomponent->processconfigpage();
        $currentcomponent->buildconfigpage();
    }

    $module_name = "";
    $module_page = "";
    $module_file = "";

    if ($display == 'index' && ($cur_menuitem['module']['rawname'] == 'builtin')) {
        $display = '';
    }

    switch ($display) {
        case 'modules':
            $module_name = 'modules';
            $module_page = $cur_menuitem['display'];
            $possibilites = array('userdisplay', 'extdisplay', 'id', 'itemid', 'selection');
            $itemid = '';
            foreach ($possibilites as $possibility) {
                if (isset($_REQUEST[$possibility]) && $_REQUEST[$possibility] != '') {
                    $itemid = htmlspecialchars($_REQUEST[$possibility], ENT_QUOTES);
                    $_REQUEST[$possibility] = $itemid;
                }
            }
            if ($itemid == 'process') {
                $quietmode = true;
                $dieafterquiet = true;
            }
            $display = 'modules';
            $type = 'setup';
            include $dirname . '/page.modules.php';
            break;
        case 'noaccess':
            show_view($amp_conf['VIEW_NOACCESS'], array('amp_conf' => &$amp_conf));
            break;
        case 'noauth':
            $config_vars['obe_error_msg'] = array();
            break;
        case 'badrefer':
            $return_HTML .= load_view($amp_conf['VIEW_BAD_REFFERER'], $amp_conf);
            break;
        case '':
            if ($astman) {
                show_view($amp_conf['VIEW_WELCOME'], array('AMP_CONF' => &$amp_conf));
            } else {
                show_view($amp_conf['VIEW_WELCOME_NOMANAGER'], array('mgruser' => $amp_conf["AMPMGRUSER"]));
            }
            break;
        default:
            $module_name = $cur_menuitem['module']['rawname'];
            $module_page = $cur_menuitem['display'];
            $module_file = 'modules/' . $module_name . '/page.' . $module_page . '.php';
            if (file_exists($dirname . "/" . $module_file)) {
                modgettext::textdomain($module_name);
                if (array_key_exists($module_file, $api_modules)) {
                    $endpoint = $api_modules[$module_file];
                    renderPpbxAPIFrame($endpoint);
                } else {
                    include($dirname . "/" . $module_file);
                }
            } else {
                $return_HTML .= "404 Not found (" . $module_file . ')';
            }
            if (isset($currentcomponent)) {
                $return_CONFIG_HTML = $currentcomponent->generateconfigpage();
            }
            break;
    }

    if ($quietmode) {
        ob_end_flush();
        if ($dieafterquiet == true) {
            die();
        }
    } else {
        $_SESSION['module_name'] = $module_name;
        $_SESSION['module_page'] = $module_page;
        $page_content = ob_get_contents();
        ob_end_clean();

        if (isset($module_name)) {
            $return_HTML .= framework_include_css_issabelpbx();
        }

        $apply = isset($arrLang['Apply changes']) ? $arrLang['Apply changes'] : 'Apply changes';
        $vars = array("applyconfig" => $apply);
        $return_HTML .= load_view("$local_templates_dir/menu.php", $vars);
        $return_HTML .= $page_content . (isset($return_CONFIG_HTML) ? $return_CONFIG_HTML : '');

        $extmap = framework_get_extmap(true);
        $reload_needed = check_reload_needed();
        $return_HTML .= load_view("$local_templates_dir/footer.php", null);

        if ($withList) {
            $dsnAsterisk = generarDSNSistema('asteriskuser', 'asterisk');
            $pDB = new paloDB($dsnAsterisk);
            $pDBsq = new paloDB($arrConf['issabel_dsn']['acl']);
            $pACL = new paloACL($pDBsq);
            $user = isset($_SESSION['issabel_user']) ? $_SESSION['issabel_user'] : "";

            $itemcat = array();
            $categories = array();
            $categories['Basic'] = array('extensions', 'featurecodeadmin', 'routing', 'trunks');
            $categories['Inbound Call Control'] = array('did', 'dahdichandids');
            $categories['Settings'] = array('advancedsettings');
            foreach ($categories as $key => $val) {
                foreach ($val as $opt) {
                    $itemcat[$opt] = $key;
                }
            }

            $exclude = array('users', 'devices', 'ampusers', 'wiki');
            $menuorder = array('Basic', 'Inbound Call Control', 'Internal Options & Configuration', 'Remote Access', 'Advanced');

            $query = "SELECT `data` FROM `module_xml` WHERE `id` = 'mod_serialized'";
            $module_serialized = $pDB->getFirstRowQuery($query, false, array());
            $unserialized = unserialize($module_serialized[0]);

            $allprivs = array();
            $id_resource = $pACL->getIdResource('pbxadmin');
            $id_group = $pACL->getIdGroup('administrator');
            if ($id_group === false) {
                $id_group = 1;
            }
            $privs = $pACL->getModulePrivileges('pbxadmin');
            foreach ($privs as $idx => $priv) {
                $allprivs[] = $priv['privilege'];
            }

            $menu = array();
            foreach ($unserialized as $modulekey => $moduledata) {
                if (isset($moduledata['embedcategory'])) {
                    foreach ($moduledata['menuitems'] as $urlkey => $name) {
                        if (in_array($urlkey, $exclude)) {
                            continue;
                        }
                        if (!$pACL->hasModulePrivilege($user, 'pbxadmin', $urlkey)) {
                            continue;
                        }

                        if (isset($itemcat[$urlkey])) {
                            $cate = $itemcat[$urlkey];
                        } else {
                            if (preg_match('/settings$/', $urlkey) || preg_match('/admin$/', $urlkey)) {
                                $cate = 'Settings';
                            } else {
                                $cate = $moduledata['embedcategory'];
                            }
                        }

                        $cate = trim(preg_replace('/\s\s+/', ' ', $cate));
                        if (!in_array($cate, $menuorder)) {
                            $menuorder[] = $cate;
                        }

                        $idx = array_search($cate, $menuorder);
                        if ($idx === false) {
                            $menuorder[] = $cate;
                            $idx = array_search($cate, $menuorder);
                        }

                        if ($moduledata['status'] == 2) {
                            if (!isset($menu[$idx]) || !is_array($menu[$idx])) {
                                $menu[$idx] = array();
                            }
                            if (!isset($menu[$idx][$cate]) || !is_array($menu[$idx][$cate])) {
                                $menu[$idx][$cate] = array();
                            }
                            $menu[$idx][$cate][] = array(
                                'urlkey' => $urlkey,
                                'name' => _tr($name),
                                'category' => $cate
                            );
                        }
                    }
                }
            }

            if (!empty($menu)) {
                ksort($menu);
            }

            $menu_sorted = array();
            foreach ($menu as $idx => $datita) {
                foreach ($datita as $cate => $items) {
                    usort($items, function ($a, $b) {
                        if ($a['name'] == $b['name']) {
                            return 0;
                        }
                        return ($a['name'] < $b['name']) ? -1 : 1;
                    });
                    $menu_sorted[_tr($cate)] = $items;
                }
            }

            if (!$pACL->hasModulePrivilege($user, 'pbxadmin', $display)) {
                $return_HTML = '';
            }

            $smarty->assign('leftmenu', $menu_sorted);
            $smarty->assign("htmlFPBX", $return_HTML);
            return $smarty->fetch("$local_templates_dir/main.tpl");
        }
    }
}

function framework_include_css_issabelpbx()
{
    global $active_modules, $module_name, $module_page, $amp_conf;
    $version = get_framework_version();
    $version_tag = '?load_version=' . urlencode($version);
    if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
        $this_time_append = '.' . time();
        $version_tag .= $this_time_append;
    } else {
        $this_time_append = '';
    }

    $html = '';
    $view_module_version = isset($active_modules[$module_name]['version'])
        ? $active_modules[$module_name]['version']
        : $version_tag;
    $mod_version_tag = '&load_version=' . urlencode($view_module_version);
    if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
        $mod_version_tag .= $this_time_append;
    }

    if (is_file('admin/modules/' . $module_name . '/' . $module_name . '.css')) {
        $html .= '<link href="' . $_SERVER['PHP_SELF']
            . '?handler=file&amp;module=' . $module_name
            . '&amp;file=' . $module_name . '.css' . $mod_version_tag
            . '" rel="stylesheet" type="text/css" />';
    }
    if (isset($module_page) && ($module_page != $module_name) && is_file('admin/modules/' . $module_name . '/' . $module_page . '.css')) {
        $html .= '<link href="' . $_SERVER['PHP_SELF']
            . '?handler=file&amp;module=' . $module_name
            . '&amp;file=' . $module_page . '.css' . $mod_version_tag
            . '" rel="stylesheet" type="text/css" />';
    }

    return $html;
}

function framework_include_js_issabelpbx($module_name, $module_page)
{
    return '';
}

function renderPpbxAPIFrame($endpoint)
{
    echo "<iframe src='/newgui/$endpoint.html'frameborder=0 style='width:100%; height:100px;' id='iFrame1' ></iframe>";
}

