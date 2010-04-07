<?php
/**
 * DokuWiki Plugin Email Digest
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_emaildigest extends DokuWiki_Action_Plugin {
    /**
     * Class variables
     */
    var $_start   = array();
    var $_end     = 0;
    var $_hour    = 0;
    var $_wdays   = array();
    var $_enabled = array();
    var $_skip    = array();

    /**
     * Returns some info
     */
    function getInfo() {
        return confToHash(DOKU_PLUGIN.'emaildigest/plugin.info.txt');
    }

    /**
     * Registers event handlers
     */
    function register(&$controller) {
        $controller->register_hook('AUTH_USER_CHANGE',  'AFTER',  $this, '_addUserModsLogEntry');
        $controller->register_hook('MAIL_MESSAGE_SEND', 'BEFORE', $this, '_stopNotification');
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, '_sendEmailDigest');
    }

    /**
     * Adds an entry to the usermods changelog
     */
    function _addUserModsLogEntry(&$event) {
        global $conf;

        // modification succeeded?
        if (empty($event->data['modification_result'])) return;

        // registernotify enabled?
        $enable = explode(',', $this->getConf('enable'));
        if (empty($conf['registernotify'])
                || !in_array('register', $enable)) return;

        // prepare changelog components
        // (compatible with page/media changelog format)
        $logline = array(
            'date'  => time(),
            'ip'    => clientIP('single'),
            'type'  => '',
            'id'    => '', // 'id' is an user id, not a page id
            'user'  => !empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : '',
            'sum'   => '',
            'extra' => '',
        );

        // add extra info if modified by admin via usermanager plugin
        foreach (debug_backtrace() as $trace) {
            if (empty($trace['class'])) continue;
            if ($trace['class'] !== 'admin_plugin_usermanager') continue;
            $logline['extra'] = 'by-admin';
            break;
        }

        // build changelog line
        switch ($event->data['type']) {
            case 'create':
                list($user, $pass, $name, $mail, $grps) = $event->data['params'];
                $logline['type'] = 'C';
                $logline['id'] = $user;

                if (!is_array($grps)) $grps = array($conf['defaultgroup']);

                $info = array();
                $info[] = 'user:'.$user;
                $info[] = 'pass:*';
                $info[] = 'name:'.$name;
                $info[] = 'mail:'.$mail;
                $info[] = 'grps:'.implode(',', $grps);

                $logline['sum'] = implode(', ', $info);
                break;
            case 'modify':
                list($user, $data) = $event->data['params'];
                $logline['type'] = 'E';
                $logline['id'] = $user;

                $mods = array();
                if (isset($data['user'])) $mods[] = 'user:'.$data['user'];
                if (isset($data['pass'])) $mods[] = 'pass:*';
                if (isset($data['name'])) $mods[] = 'name:'.$data['name'];
                if (isset($data['mail'])) $mods[] = 'mail:'.$data['mail'];
                if (isset($data['grps'])) $mods[] = 'grps:'.implode(',', $data['grps']);
                if (empty($mods)) return;

                $logline['sum'] = implode(', ', $mods);
                break;
            case 'delete':
                $logline['type'] = 'D';
                $logline['id'] = implode(',', $event->data['params'][0]);
                break;
            default:
                return;
        }
        $logline = implode("\t", $logline).DOKU_LF;

        // save usermods changelog
        $this->_saveData('usermods', $logline, 'no_serialize', 'append');
    }

    /**
     * Stops default notification emails
     */
    function _stopNotification(&$event) {
        if (!$enable = explode(',', $this->getConf('enable'))) return;

        $stop = false;
        foreach (debug_backtrace() as $trace) {
            if (isset($trace['class'])) continue;
            switch ($trace['function']) {
                case 'notify':
                    if (in_array($trace['args'][1], $enable)) $stop = true;
                    break 2;
                case 'media_notify':
                    if (in_array('admin', $enable)) $stop = true;
                    break 2;
                case 'subscription_send': // develonly@2010/04
                    if (in_array('subscribers', $enable)) $stop = true;
                    break 2;
            }
        }
        if ($stop) $event->preventDefault();
    }

    /**
     * Sends email digest
     */
    function _sendEmailDigest(&$event) {
        global $conf;

        // need to notify? try to enter exclusive mode
        if ($this->_needNotify() && $this->_lock()) {
            // recheck after lock file created just in case
            $this->_needNotify();

            $this->_skip = array_flip(array_intersect(
                array('sub_media', 'sub_minoredit', 'sub_selfmod', 'sub_hidden', 'reg_by_admin', 'reg_not_reg'),
                explode(',', $this->getConf('skip'))
            ));

            $logfiles = array(
                'admin' => array(
                    'page'  => $conf['changelog'],
                    'media' => $conf['media_changelog'],
                ),
                'subscribers' => array(
                    'page'  => $conf['changelog'],
                    'media' => $conf['media_changelog'],
                ),
                'register' => array(
                    'user'  => metaFN('plugin_emaildigest', '.usermods'),
                ),
            );

            // get updates and notify
            foreach (array_keys($this->_enabled) as $type) {
                $updates = $this->_getUpdates(
                    $this->_start[$type], $this->_end, $logfiles[$type], $type
                );
                if (empty($updates) || $this->_notify($type, $updates)) {
                    $next_start = $this->_end + 1;
                    $this->_saveData('next_'.$type, $next_start);
                    if ($type === 'register') {
                        $this->_trimUserModsLog($next_start);
                    }
                }
            }
            $this->_unlock();
        }

        // prevent sendDigest() in lib/exe/indexer.php (develonly@2010/04)
        if (function_exists('sendDigest')
                && in_array('subscribers', explode(',', $this->getConf('enable')))) {
            $conf['subscribers'] = '0';
        }
    }

    /**
     * Checks if there is a need to notify
     */
    function _needNotify() {
        global $conf;
        static $now;

        if (is_null($now)) $now = time();

        // check if special url parameter is required
        if ($this->getConf('url_param')
                && !isset($_REQUEST[$this->getConf('url_param')])) {
            $this->_debug('Required URL parameter not set');
            return false;
        }

        // digest notification scheduled?
        $this->_hour  = (int) $this->getConf('send_hour') % 24;
        $this->_wdays = array_intersect(
            array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'),
            explode(',', $this->getConf('send_wdays'))
        );
        if (empty($this->_wdays)) {
            $this->_debug('Digest notification not scheduled');
            return false;
        }

        // digest notification features enabled?
        $requisites = array(
            'admin'       => $conf['notify'],
            'subscribers' => $conf['subscribers'],
            'register'    => $conf['registernotify'],
        );
        foreach (explode(',', $this->getConf('enable')) as $type) {
            if (!empty($requisites[$type])) $enabled[$type] = true;
        }
        if (empty($enabled)) {
            $this->_debug('Digest notification feature not enabled');
            return false;
        }

        // already processed?
        $this->_end = $end = $this->_getPeriodBoundary($now) - 1;
        foreach (array_keys($enabled) as $type) {
            if (!$start = $this->_loadData('next_'.$type)) {
                $start = $this->_getPeriodBoundary($end);
            }
            if ($start > $end) {
                $this->_debug("Already processed for this period ($type)");
                unset($enabled[$type]);
            } else {
                $this->_start[$type] = $start;
            }
        }
        if (empty($enabled)) return false;

        // ok, jobs are needed
        $this->_enabled = $enabled;
        return true;
    }

    /**
     * Returns the end of the given period
     */
    function _getPeriodBoundary($time) {
        $boundaries = array();
        foreach ($this->_wdays as $wday) {
            $curr_week = strtotime('This '.$wday.' + '.$this->_hour.' hours', $time);
            $prev_week = strtotime('Last '.$wday.' + '.$this->_hour.' hours', $time);
            $boundaries[]  = ($time >= $curr_week) ? $curr_week : $prev_week;
        }
        return max($boundaries);
    }

    /**
     * Returns update logs within the period
     */
    function _getUpdates($start, $end, $logfiles, $type) {
        $updates = array();

        // get update log entries within the target period
        foreach ($logfiles as $class => $logfile) {
            $updates = array_merge($updates, $this->_getLogEntries(
                $start, $end, $logfile, $class
            ));
        }

        // apply filter ('sub_selfmod' is applied later)
        switch ($type) {
            case 'admin':
                $updates_filtered = $updates;
                break;
            case 'subscribers':
                foreach ($updates as $entry) {
                    if ((isset($this->_skip['sub_hidden']) && $entry['class'] === 'page' && isHiddenPage($entry['id']))
                            || (isset($this->_skip['sub_media']) && $entry['class'] === 'media')
                            || (isset($this->_skip['sub_minoredit']) && $entry['type'] === 'e')) {
                        continue; // skip hidden page, media file, minor edit
                    }
                    $updates_filtered[] = $entry;
                }
                break;
            case 'register':
                foreach ($updates as $entry) {
                    if ((isset($this->_skip['reg_by_admin']) && $entry['extra'] === 'by-admin')
                           || (isset($this->_skip['reg_not_reg']) && $entry['type'] !== 'C')) {
                        continue; // skip usermods by admin, other than registration
                    }
                    $updates_filtered[] = $entry;
                }
                break;
        }
        $updates = is_null($updates_filtered) ? array() : $updates_filtered;

        // any updates?
        if (empty($updates)) {
            $this->_debug("No update has been made during this period ($type)");
            $this->_debug('  '.dformat($start).' - '.dformat($end));
        } else {
            sort($updates);
        }
        return $updates;
    }

    /**
     * Returns change log entries within the target period
     */
    function _getLogEntries($start, $end, $logfile, $class = '') {
        $loglines = (array) @file($logfile);

        $entries = array();
        for ($i = count($loglines) - 1; $i >= 0; $i--) {
            $entry = parseChangelogLine($loglines[$i]);

            if ($entry === false) break;
            if ($entry['date'] < $start) break;
            if ($entry['date'] > $end) continue;

            // some fixes for plain text outputs
            $entry['sum']   = trim(strtr($entry['sum'],   "\x00\x0A\x0B\x0D", '    '));
            $entry['extra'] = trim(strtr($entry['extra'], "\x00\x0A\x0B\x0D", '    '));

            if ($class) $entry['class'] = $class;
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * Dispatches the notification request
     */
    function _notify($type, $updates) {
        global $conf;

        $to_addr = array(
            'admin'    => $conf['notify'],
            'register' => $conf['registernotify'],
        );

        switch ($type) {
            case 'admin':
            case 'register':
                $successfully_sent = $this->_sendMail(
                    $to_addr[$type],
                    $this->_buildMailSubject($type),
                    $this->_buildMailBody($type, $updates),
                    $this->_buildFromAddress($to_addr[$type])
                );
                if ($successfully_sent) {
                    $this->_debug("Digest message sent ($type)");
                    return true;
                }
                break;
            case 'subscribers':
                $this->_notifySubscribers($updates);
                return true; // no check
                break;
        }
        return false;
    }

    /**
     * Notifies subscribers
     */
    function _notifySubscribers($updates) {
        global $auth;

        // acl required
        if (!$auth) return;

        $page_ids = $media_ids = array();
        $subs = array('page' => array(), 'media' => array(), 'ns' => array(), 'user' => array());

        // collect updated page and media IDs
        foreach ($updates as $entry) {
            if ($entry['class'] === 'page') {
                $page_ids[$entry['id']] = 1;
            } elseif ($entry['class'] === 'media') {
                $media_ids[$entry['id']] = 1;
            }
        }

        // load subscribers of each page/media and its parent namespaces
        foreach (array_keys($page_ids) as $id) {
            $this->_loadSubscribers($subs, $id, 'page');
        }
        foreach (array_keys($media_ids) as $id) {
            $ns = (string) getNS($id);
            $this->_loadSubscribers($subs, $ns);
            $subs['media'][$id] = $subs['ns'][$ns];
        }

        // notify for each user (not for each update entry)
        $mail_count = 0;
        foreach (array_keys($subs['user']) as $user) {
            // user exists?
            if (!$info = $auth->getUserData($user)) continue;

            // search subscribed pages and media with acl checking
            $subscribed = array();
            foreach (array('page', 'media') as $type) {
                foreach (array_keys($subs[$type]) as $id) {
                    if (!isset($subs[$type][$id][$user])) continue;
                    $checkid = ($type === 'media') ? getNS($id).':dummyID' : $id;
                    if (auth_aclcheck($checkid, $user, $info['grps']) < AUTH_READ) continue;
                    $subscribed[$type][$id] = 1;
                }
            }
            if (empty($subscribed)) continue;

            // extract update log entries for the user
            $updates_for_user = array();
            foreach ($updates as $entry) {
                if (isset($this->_skip['sub_selfmod']) && $entry['user'] === $user) {
                    continue; // skip selfmod
                }
                if (isset($subscribed[$entry['class']][$entry['id']])) {
                    $updates_for_user[] = $entry;
                }
            }
            if (empty($updates_for_user)) continue;

            // send email digest
            $successfully_sent = $this->_sendMail(
                $info['mail'],
                $this->_buildMailSubject('subscribers'),
                $this->_buildMailBody('subscribers', $updates_for_user),
                $this->_buildFromAddress($info['mail'])
            );
            if ($successfully_sent) $mail_count++;
        }

        if ($mail_count) {
            $this->_debug("Digest message sent (subscribers: $mail_count messages)");
        }
    }

    /**
     * Loads subscribers info
     */
    function _loadSubscribers(&$subs, $id, $type = 'ns') {
        // already loaded?
        if (isset($subs[$type][$id])) {
            return $subs[$type][$id];
        } else {
            $subs[$type][$id] = array();
        }

        // load .mlist file
        $ext = ($type === 'page' || $id === '') ? '.mlist' : '/.mlist';
        if (is_readable($filename = metaFN($id, $ext))) {
            foreach (file($filename) as $line) {
                list($user) = explode(' ', rtrim($line)); // adapt to devel@2010/04
                $subs['user'][$user] = 1;
                $subs[$type][$id][$user] = 1;
            }
        }

        // recursively load .mlist file for parent namespaces
        $subs[$type][$id] = array_merge(
            $this->_loadSubscribers($subs, (string) getNS($id)),
            $subs[$type][$id]
        );
        return $subs[$type][$id];
    }

    /**
     * Builds from address (NOTE: placeholders are irresponsible)
     */
    function _buildFromAddress($dummy_addr) {
        global $conf;

        $from = $conf['mailfrom'];
        $from = str_replace('@USER@', 'superuser', $from);
        $from = str_replace('@NAME@', 'WikiAdmin', $from);
        $from = str_replace('@MAIL@', $dummy_addr, $from);
        return $from;
    }

    /**
     * Builds email subject
     */
    function _buildMailSubject($type) {
        global $conf;

        return sprintf('[%s] %s: %s',
            $conf['title'],
            $this->getLang('subject_'.$type),
            date('Y-m-d', $this->_end)
        );
    }

    /**
     * Builds email body
     */
    function _buildMailBody($type, $updates) {
        global $conf;
        static $media_exists = array();

        $counts = $urls = array('page' => array(), 'media' => array(), 'user' => array());

        // count changes
        foreach ($updates as $entry) {
            if (!isset($counts[$entry['class']][$entry['id']])) {
                $counts[$entry['class']][$entry['id']] = 0;
            }
            $counts[$entry['class']][$entry['id']]++;
            $loglines[] = $this->_buildLogLine($entry);
        }
        foreach (array('page', 'media', 'user') as $key) {
            arsort($counts[$key]);
        }

        // load template (check local template first)
        $body = io_readFile($this->localFN('mail_'.$type.'.local'), false);
        if (!$body) $body = io_readFile($this->localFN('mail_'.$type), false);

        $body = str_replace('@TITLE@',         $conf['title'],                $body);
        $body = str_replace('@DATE_START@',    dformat($this->_start[$type]), $body);
        $body = str_replace('@DATE_END@',      dformat($this->_end),          $body);
        $body = str_replace('@DOKUWIKIURL@',   DOKU_URL,                      $body);

        $body = str_replace('@PAGE_COUNTS@',   count($counts['page']),  $body);
        $body = str_replace('@MEDIA_COUNTS@',  count($counts['media']), $body);
        $body = str_replace('@USER_COUNTS@',   count($counts['user']),  $body);
        $body = str_replace('@LOG_COUNTS@',    count($loglines),        $body);

        $body = str_replace('@NOTE_SKIPPED@',  $this->_buildSkippedNote($type), $body);
        $body = str_replace('@PAGE_SUMMARY@',  $this->_buildPageSummary($counts['page'], $type), $body);
        $body = str_replace('@MEDIA_SUMMARY@', $this->_buildMediaSummary($counts['media'], $type), $body);
        $body = str_replace('@LOG_SUMMARY@',   $this->_buildLogSummary($loglines), $body);

        return $body;
    }

    /**
     * Builds one-line version of log entry
     */
    function _buildLogLine($entry) {
        static $edit_types = array(
            'C' => 'CREATE',
            'E' => 'EDIT',
            'e' => 'MINOR_EDIT',
            'D' => 'DELETE',
            'R' => 'REVERT',
        );
        return rtrim(implode(' ', array(
            dformat($entry['date']),
            $entry['user'] ? $entry['user'] : $entry['ip'],
            '['.strtoupper($entry['class']).'_'.$edit_types[$entry['type']].']',
            $entry['id'],
            $entry['sum'] ? '('.$entry['sum'].')' : ''
        )));
    }

    /**
     * Builds changelog lines
     */
    function _buildLogSummary($loglines) {
        static $output_tmpl = "%4s. %s\n";

        $item_count = $output = null;
        foreach ($loglines as $logline) {
            $output .= sprintf($output_tmpl, ++$item_count, $logline);
        }
        return isset($output) ? rtrim($output) : '';
    }

    /**
     * Builds page edit summary using the config option
     */
    function _buildPageSummary($page_counts, $type) {
        static $link_opt;
        static $urls = array();
        static $urlparam_tmpl = array(
            'diff'    => 'do%%5Bdiff%%5D,rev2%%5B%%5D=%s,rev2%%5B%%5D=%s',
            'rev'     => 'rev=%s',
            'revlist' => 'do=revisions',
            'current' => '',
        );

        if (is_null($link_opt)) $link_opt = $this->getConf('link_page');
        $output = $item_count = null;

        if (!isset($urlparam_tmpl[$link_opt])) {
            // without url info
            $output_tmpl = "%4s. %s (%s)\n";
            foreach ($page_counts as $id => $edit_count) {
                $output .= sprintf($output_tmpl, ++$item_count, $id, $edit_count);
            }
        } elseif (in_array($link_opt, array('revlist', 'current'))) {
            // with fixed url
            $output_tmpl = "%4s. %s (%s)\n%4s  %s\n";
            foreach ($page_counts as $id => $edit_count) {
                if (!isset($urls[$type][$id])) {
                    $urls[$type][$id] = wl($id, $urlparam_tmpl[$link_opt], 'absolute', '&');
                }
                $output .= sprintf($output_tmpl, ++$item_count, $id, $edit_count, '', $urls[$type][$id]);
            }
        } elseif (in_array($link_opt, array('diff', 'rev'))) {
            // with varied url
            $output_tmpl = "%4s. %s (%s)\n%4s  %s\n";
            foreach ($page_counts as $id => $edit_count) {
                if (!isset($urls[$type][$id])) {
                    // scan base revision (prev_last) for the current period
                    $curr_last = $prev_last = null;
                    foreach (getRevisions($id, -1, 0) as $rev) {
                        if (is_null($curr_last) && $rev <= $this->_end) {
                            $curr_last = $rev;
                        } elseif (is_null($prev_last) && $rev < $this->_start[$type]) {
                            $prev_last = $rev;
                            break;
                        }
                    }
                    if (is_null($curr_last)) $curr_last = 1; // something wrong, but go on
                    if (is_null($prev_last)) $prev_last = 1; // compare newly created page with rev 1970-01-01
                    $urlparam = sprintf($urlparam_tmpl[$link_opt], $curr_last, $prev_last);
                    $urls[$type][$id] = wl($id, $urlparam, 'absolute', '&');
                }
                $output .= sprintf($output_tmpl, ++$item_count, $id, $edit_count, '', $urls[$type][$id]);
            }
        }
        return isset($output) ? rtrim($this->getLang('note_edit_count').$output) : '';
    }

    /**
     * Builds media edit summary using the config option
     */
    function _buildMediaSummary($media_counts, $type) {
        static $link_opt;
        static $urls = array();
        static $exists = array();

        if (is_null($link_opt)) $link_opt = $this->getConf('link_media');
        $output = $item_count = null;

        if ($link_opt === 'none') {
            $output_tmpl = "%4s. %s (%s)\n";
            foreach ($media_counts as $id => $edit_count) {
                $output .= sprintf($output_tmpl, ++$item_count, $id, $edit_count);
            }
        } elseif (in_array($link_opt, array('direct', 'detail'))) {
            $output_tmpl = "%4s. %s (%s)\n%4s  %s\n";
            foreach ($media_counts as $id => $edit_count) {
                if (!isset($exists[$id])) $exists[$id] = file_exists(mediaFN($id));
                if (!$exists[$id]) {
                    $urls[$id] = $this->getLang('media_deleted');
                } elseif ($link_opt === 'direct') {
                    $urls[$id] = ml($id, '', 'direct', '&', 'absolute');
                } elseif ($link_opt === 'detail') {
                    list($ext, $mime, $dl) = mimetype(mediaFN($id), false);
                    $direct = (substr($mime, 0, 5) == 'image') ? false : true;
                    $urls[$id] = ml($id, '', $direct, '&', 'absolute');
                }
                $output .= sprintf($output_tmpl, ++$item_count, $id, $edit_count, '', $urls[$id]);
            }
        }
        return isset($output) ? rtrim($this->getLang('note_edit_count').$output) : '';
    }

    /**
     * Builds a note on skipped entries
     */
    function _buildSkippedNote($type) {
        if ($type === 'admin' || empty($this->_skip)) return '';

        $type_shortened = substr($type, 0, 3);

        $output = '';
        foreach (array_keys($this->_skip) as $skipped) {
            if (strpos($skipped, $type_shortened) === 0) {
                $output .= '    - '.$this->getLang($skipped)."\n";
            }
        }
        return $output ? "\n".$this->getLang('note_skipped').$output : '';
    }

    /**
     * Trims recent usermods log file
     */
    function _trimUserModsLog($next_start) {
        global $conf;

        // get raw loglines
        $rawlines = $this->_loadData('usermods', 'no_unserialize');

        // extract necessary loglines
        $recent_usermods = $this->_getLogEntries(
            $next_start - $conf['recent_days'] * 86400,
            time(),
            metaFN('plugin_emaildigest', '.usermods')
        );

        // no need to trim
        if (count($rawlines) === count($recent_usermods)) return;

        $loglines = array();
        foreach ($recent_usermods as $usermod) {
            $loglines[] = implode("\t", $usermod).DOKU_LF;
        }
        sort($loglines);

        // overwrite data file
        $this->_saveData('usermods', implode('', $loglines), 'no_serialize');
    }

    /**
     * Creates a lock file which is valid for 60 seconds
     */
    function _lock() {
        global $conf;

        $lockfile = $conf['lockdir'].'/_plugin_emaildigest_.lock';

        if (file_exists($lockfile)) {
            if (time() - filemtime($lockfile) < 60) {
                return false;
            } else {
                @unlink($lockfile);
            }
        }

        // create lock file
        if (io_saveFile($lockfile, 'dummy_text')) {
            @ignore_user_abort(1);
            @set_time_limit(60);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Removes a lock file
     */
    function _unlock() {
        global $conf;

        $lockfile = $conf['lockdir'].'/_plugin_emaildigest_.lock';
        @unlink($lockfile);
        @ignore_user_abort(0);
    }

    /**
     * Loads "plugin_emaildigest.*" meta data
     */
    function _loadData($type, $no_unserialize = false) {
        $datafile = metaFN('plugin_emaildigest', ".$type");

        if (!is_readable($datafile)) {
            return false;
        } elseif ($no_unserialize) {
            return @file($datafile);
        } else {
            return unserialize(@file_get_contents($datafile));
        }
    }

    /**
     * Saves "plugin_emaildigest.*" meta data
     */
    function _saveData($type, $data, $no_serialize = false, $append = false) {
        $datafile = metaFN('plugin_emaildigest', ".$type");

        if (!$no_serialize) $data = serialize($data);
        if (!$this->getConf('test_only')) {
            io_saveFile($datafile, $data, $append);
        }
    }

    /**
     * Sends mail
     */
    function _sendMail($to, $subject, $body, $from = '', $cc = '', $bcc = '', $headers = null, $params = null) {
        if ($this->getConf('test_only')) {
            $this->_debug(implode("\n", array(
                "\n----------",
                "From   : $from",
                "To     : $to",
                "Cc     : $cc",
                "Bcc    : $bcc",
                "Subject: $subject",
                "----------\n",
                $body,
                "----------",
            )));
            return true;
        }
        return mail_send($to, $subject, $body, $from, $cc, $bcc, $headers, $params);
    }

    /**
     * Prints debug message
     */
    function _debug($str) {
        if (!isset($_REQUEST['debug'])) return;
        print "plugin_emaildigest: $str\n";
    }
}
