<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Template displayed on contact/thirdparty cards for Brevo integration.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

/** @var Form $form */
$form = isset($form) ? $form : null;
$token = newToken();
$cardUrl = $_SERVER['PHP_SELF'];
if (in_array('thirdpartycard', $context)) {
    $cardUrl .= '?socid='.(int) $object->id;
} else {
    $cardUrl .= '?id='.(int) $object->id;
}
?>
<div class="card mt-3">
    <div class="card-header">
        <span class="fa fa-paper-plane"></span> <?php echo dol_escape_htmltag($langs->trans('BrevoIntegrationTitle')); ?>
    </div>
    <div class="card-body">
        <?php if ($listsError !== '') { ?>
            <div class="warning"><?php echo dol_escape_htmltag($listsError); ?></div>
        <?php } elseif (empty($lists)) { ?>
            <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoNoListFound')); ?></div>
        <?php } else { ?>
            <form method="post" action="<?php echo dol_escape_htmltag($cardUrl); ?>">
                <input type="hidden" name="token" value="<?php echo $token; ?>" />
                <input type="hidden" name="brevo_action" value="push" />
                <div class="form-group">
                    <label for="brevo_list_id" class="control-label"><?php echo dol_escape_htmltag($langs->trans('BrevoSelectListLabel')); ?></label>
                    <select name="brevo_list_id" id="brevo_list_id" class="flat">
                        <option value="0"><?php echo dol_escape_htmltag($langs->trans('Select')); ?></option>
                        <?php foreach ($lists as $list) { ?>
                            <option value="<?php echo (int) $list['id']; ?>"><?php echo dol_escape_htmltag($list['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="butAction"><?php echo dol_escape_htmltag($langs->trans('BrevoPushButton')); ?></button>
                </div>
            </form>
        <?php } ?>

        <?php if (!empty($syncEntries)) { ?>
            <h4><?php echo dol_escape_htmltag($langs->trans('BrevoCurrentSubscriptions')); ?></h4>
            <table class="noborder centpercent">
                <thead>
                    <tr class="liste_titre">
                        <th><?php echo dol_escape_htmltag($langs->trans('BrevoListId')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('BrevoStatus')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('Date')); ?></th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($syncEntries as $entry) { ?>
                        <tr>
                            <td><?php echo (int) $entry['brevo_list_id']; ?></td>
                            <td><?php echo dol_escape_htmltag($langs->trans('BrevoStatus'.ucfirst($entry['status']))); ?></td>
                            <td><?php echo dol_escape_htmltag(dol_print_date($entry['date_sync'], 'dayhour')); ?></td>
                            <td class="right">
                                <?php if ($entry['status'] !== 'removed') { ?>
                                    <form method="post" action="<?php echo dol_escape_htmltag($cardUrl); ?>">
                                        <input type="hidden" name="token" value="<?php echo $token; ?>" />
                                        <input type="hidden" name="brevo_action" value="remove" />
                                        <input type="hidden" name="brevo_list_id" value="<?php echo (int) $entry['brevo_list_id']; ?>" />
                                        <button type="submit" class="butActionDelete smallpaddingimp"><?php echo dol_escape_htmltag($langs->trans('BrevoRemoveButton')); ?></button>
                                    </form>
                                <?php } else { ?>
                                    <span class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoAlreadyRemoved')); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <p class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoNoSyncHistory')); ?></p>
        <?php } ?>
    </div>
</div>
