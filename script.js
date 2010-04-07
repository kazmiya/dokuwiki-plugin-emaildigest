/**
 * Email Digest Plugin for DokuWiki / script.js
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

// hide unwanted input box
addInitEvent(function() {
    var is_config_manager = $('config__manager');
    if (!is_config_manager) return;

    var hideInput = function(id) {
        var input = $(id);
        if (!input) return;
        input.parentNode.style.display = 'none';
    };
    hideInput('config___plugin____emaildigest____enable_other');
    hideInput('config___plugin____emaildigest____send_wdays_other');
    hideInput('config___plugin____emaildigest____skip_other');
});
