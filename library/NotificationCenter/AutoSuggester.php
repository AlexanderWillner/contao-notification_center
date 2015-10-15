<?php

/**
 * notification_center extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2015, terminal42
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    LGPL
 */

namespace NotificationCenter;

use NotificationCenter\Model\Notification as NotificationModel;


class AutoSuggester extends \Controller
{

    protected static $strTable;
    protected static $strType;

    public static function load($dc)
    {
        // Already initialized (e.g. for another DCA)
        // This could be if Contao loads a ptable or ctable
        if (null !== static::$strTable) {
            return;
        }

        // @todo implement editAll and overrideAll
        if (\Input::get('act') != 'edit') {
            return;
        }

        // @todo rename to nc_tokens
        \System::loadLanguageFile('tokens');
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/notification_center/assets/autosuggester' . ($GLOBALS['TL_CONFIG']['debugMode'] ? '' : '.min') . '.js';
        $GLOBALS['TL_CSS'][]        = 'system/modules/notification_center/assets/autosuggester' . ($GLOBALS['TL_CONFIG']['debugMode'] ? '' : '.min') . '.css';

        static::$strTable = $dc->table;
        static::$strType  = \Database::getInstance()->prepare($GLOBALS['TL_DCA'][static::$strTable]['config']['nc_type_query'])->execute($dc->id)->type;

        foreach ($GLOBALS['TL_DCA'][static::$strTable]['fields'] as $field => $arrConfig) {
            if ($arrConfig['eval']['rgxp'] == 'nc_tokens') {
                $GLOBALS['TL_DCA'][static::$strTable]['fields'][$field]['wizard'][] = array('NotificationCenter\AutoSuggester', 'init');
            }
        }
    }


    /**
     * Initialize the auto suggester
     * @param   \DataContainer
     * @return  string
     */
    public function init(\DataContainer $dc)
    {
        $strGroup  = NotificationModel::findGroupForType(static::$strType);
        $arrTokens = $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'][$strGroup][static::$strType][$dc->field];

        if (!is_array($arrTokens) || empty($arrTokens)) {
            return '';
        }

        $arrParsedTokens = array();

        foreach ($arrTokens as $strToken) {
            $arrParsedTokens[] = array
            (
                'value'   => '##' . $strToken . '##',
                'content' => $GLOBALS['TL_LANG']['NOTIFICATION_CENTER_TOKEN'][static::$strType][$strToken] ?: ''
            );
        }

        $GLOBALS['TL_MOOTOOLS'][] = "
<script>
window.addEvent('domready', function() {
	new AutoSuggester($('ctrl_" . $dc->field . "'), " . json_encode($arrParsedTokens) . ");
});
</script>";

        return '';
    }


    /**
     * Verify tokens
     * @param   string Text
     * @param   \DataContainer
     */
    public function verifyTokens($rgxp, $strText, $objWidget)
    {
        if ($rgxp != 'nc_tokens') {
            return false;
        }

        // Check if tokens contain invalid characters
        preg_match_all('@##([^##]+)##@', $strText, $matches);

        if (!is_array($matches) || empty($matches)) {
            return true;
        }

        if (empty($matches[1])) {
            return true;
        }

        foreach ($matches[1] as $match) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $match)) {
                $objWidget->addError($GLOBALS['TL_LANG']['tl_nc_language']['token_character_error']);

                return true;
            }
        }

        // Check if tokens are valid in terms of characters but are not allowed here
        $strGroup       = NotificationModel::findGroupForType(static::$strType);
        $arrValidTokens = (array) $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'][$strGroup][static::$strType][$objWidget->name];

        // Build regex pattern
        $strPattern = '/##(' . implode('|', $arrValidTokens) . ')##/i';
        $strPattern = str_replace('*', '[^##]*', $strPattern);

        preg_match_all($strPattern, $strText, $arrValidMatches);
        preg_match_all('/##([A-Za-z0-9_]+)##/i', $strText, $arrAllMatches);

        $arrInvalidTokens = array_diff($arrAllMatches[1], $arrValidMatches[1]);

        if (count($arrInvalidTokens)) {
            $strInvalidTokens = '##' . implode('##, ##', $arrInvalidTokens) . '##';
            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['tl_nc_language']['token_error'], $strInvalidTokens));
        }

        return true;
    }
}
