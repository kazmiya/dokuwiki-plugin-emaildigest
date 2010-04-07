<?php
/**
 * Default configuration for emaildigest plugin
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

$conf['enable']     = 'admin,register,subscribers';
$conf['url_param']  = 'send_email_digest';
$conf['send_wdays'] = 'sun,mon,tue,wed,thu,fri,sat';
$conf['send_hour']  = 0;
$conf['link_page']  = 'diff';
$conf['link_media'] = 'direct';
$conf['skip']       = '';
$conf['test_only']  = 0;
