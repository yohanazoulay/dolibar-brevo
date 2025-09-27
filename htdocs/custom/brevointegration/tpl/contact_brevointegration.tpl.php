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
$categorySummary = isset($categorySummary) && is_array($categorySummary) ? $categorySummary : array();
$contactCategories = isset($contactCategories) && is_array($contactCategories) ? $contactCategories : array();
$canSyncCategories = !empty($canSyncCategories);
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

        <?php if (!empty($categorySummary) || !empty($contactCategories)) { ?>
            <div class="mtopmore">
                <h4><?php echo dol_escape_htmltag($langs->trans('BrevoCategorySyncTitle')); ?></h4>
                <?php if (!empty($categorySummary)) { ?>
                    <p class="opacitymedium"><?php echo $langs->trans('BrevoCategorySyncDescription'); ?></p>
                    <ul class="list-unstyled">
                        <?php foreach ($categorySummary as $categoryItem) { ?>
                            <li class="mb1">
                                <strong><?php echo dol_escape_htmltag($categoryItem['category_label'] !== '' ? $categoryItem['category_label'] : $langs->trans('BrevoCategorySyncUnnamed', $categoryItem['category_id'])); ?></strong>
                                <?php if (!empty($categoryItem['lists'])) { ?>
                                    <div class="opacitymedium">
                                        <?php
                                        $listLabels = array();
                                        foreach ($categoryItem['lists'] as $listInfo) {
                                            $label = isset($listInfo['label']) && $listInfo['label'] !== '' ? $listInfo['label'] : $langs->trans('BrevoCategorySyncListFallback', (int) $listInfo['id']);
                                            $listLabels[] = dol_escape_htmltag($label);
                                        }
                                        echo implode(', ', $listLabels);
                                        ?>
                                    </div>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if ($canSyncCategories) { ?>
                        <form method="post" action="<?php echo dol_escape_htmltag($cardUrl); ?>" class="inline-block">
                            <input type="hidden" name="token" value="<?php echo $token; ?>" />
                            <input type="hidden" name="brevo_action" value="sync_categories" />
                            <button type="submit" class="butAction"><?php echo dol_escape_htmltag($langs->trans('BrevoSyncCategoriesButton')); ?></button>
                        </form>
                    <?php } else { ?>
                        <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoSyncCategoriesNoPermission')); ?></div>
                    <?php } ?>
                <?php } elseif (!empty($contactCategories)) { ?>
                    <p class="opacitymedium"><?php echo $langs->trans('BrevoSyncCategoriesNoMapping'); ?></p>
                    <ul class="list-unstyled">
                        <?php foreach ($contactCategories as $label) { ?>
                            <li><?php echo dol_escape_htmltag($label); ?></li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="opacitymedium"><?php echo $langs->trans('BrevoSyncCategoriesNoCategory'); ?></p>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (!empty($syncEntries)) { ?>
            <h4><?php echo dol_escape_htmltag($langs->trans('BrevoCurrentSubscriptions')); ?></h4>
            <table class="noborder centpercent">
                <thead>
                    <tr class="liste_titre">
                        <th><?php echo dol_escape_htmltag($langs->trans('BrevoListLabel')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('BrevoStatus')); ?></th>
                        <th><?php echo dol_escape_htmltag($langs->trans('Date')); ?></th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($syncEntries as $entry) { ?>
                        <tr>
                            <td>
                                <?php if (!empty($entry['brevo_list_label'])) { ?>
                                    <?php echo dol_escape_htmltag($entry['brevo_list_label']); ?>
                                    <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoListId')); ?> : <?php echo (int) $entry['brevo_list_id']; ?></div>
                                <?php } else { ?>
                                    <?php echo (int) $entry['brevo_list_id']; ?>
                                <?php } ?>
                            </td>
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
