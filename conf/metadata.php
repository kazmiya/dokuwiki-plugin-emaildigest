<?php
/**
 * Configuration metadata for emaildigest plugin
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

$meta['enable']     = array('multicheckbox',
                            '_choices' => array('admin', 'register', 'subscribers'));
$meta['url_param']  = array('string',
                            '_pattern' => '/^[\w-]*$/');
$meta['send_wdays'] = array('multicheckbox',
                            '_choices' => array('mon', 'fri', 'tue', 'sat', 'wed', 'sun', 'thu'));
$meta['send_hour']  = array('numeric', // 0 - 23
                            '_pattern' => '/^(?:1?[0-9]|2[0-3])$/');
$meta['link_page']  = array('multichoice',
                            '_choices' => array('diff', 'rev', 'revlist', 'current', 'none'));
$meta['link_media'] = array('multichoice',
                            '_choices' => array('direct', 'detail', 'none'));
$meta['skip']       = array('multicheckbox',
                            '_choices' => array('sub_media', 'sub_minoredit', 'sub_selfmod', 'sub_hidden', 'reg_by_admin', 'reg_not_reg'));
$meta['test_only']  = array('onoff');
