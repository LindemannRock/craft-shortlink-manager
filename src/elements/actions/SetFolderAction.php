<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\elements\actions;

use Craft;
use craft\base\ElementAction;
use lindemannrock\shortlinkmanager\ShortLinkManager;

class SetFolderAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('shortlink-manager', 'Set Folder...');
    }

    public function getTriggerHtml(): ?string
    {
        $folderOptions = [];
        foreach (ShortLinkManager::$plugin->taxonomy->getFolderOptions() as $id => $name) {
            $folderOptions[] = [
                'value' => (int)$id,
                'label' => (string)$name,
            ];
        }

        Craft::$app->getView()->registerJsWithVars(fn($type, $folderData) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: true,
        activate: async (selectedItems, elementIndex) => {
            const ids = (typeof elementIndex.getSelectedElementIds === 'function'
                ? elementIndex.getSelectedElementIds()
                : []
            ).map((id) => Number(id)).filter((id) => id > 0);

            if (!ids.length) {
                return;
            }
            const container = $('<div/>');
            const folderOptions = $folderData;

            const existingWrap = $('<div/>');
            const select = Craft.ui.createSelect({
                options: [{ label: Craft.t('app', 'Select...'), value: '' }, ...folderOptions],
            }).appendTo(existingWrap);
            Craft.ui.createField(existingWrap, {
                label: Craft.t('shortlink-manager', 'Existing folders'),
            }).appendTo(container);

            const createWrap = $('<div/>');
            const input = $('<input/>', {
                class: 'text fullwidth',
                type: 'text',
                placeholder: Craft.t('shortlink-manager', 'Enter folder name'),
            }).appendTo(createWrap);
            Craft.ui.createField(createWrap, {
                label: Craft.t('shortlink-manager', 'Or create new folder'),
            }).appendTo(container);

            const buttonRow = $('<div/>', { class: 'buttons right' }).appendTo(container);
            const button = Craft.ui.createSubmitButton({
                label: Craft.t('app', 'Save'),
                spinner: true,
            }).appendTo(buttonRow);
            const hud = new Garnish.HUD(elementIndex.\$actionMenuBtn, container);
            setTimeout(() => input.trigger('focus'), 50);

            const refreshFolderSelect = (nextFolders, selected = '') => {
                folderOptions.length = 0;
                nextFolders.forEach((item) => folderOptions.push(item));
                const nativeSelect = select.find('select');
                nativeSelect.empty();
                $('<option/>', { value: '', text: Craft.t('app', 'Select...') }).appendTo(nativeSelect);
                folderOptions.forEach((option) => {
                    $('<option/>', { value: String(option.value), text: String(option.label) }).appendTo(nativeSelect);
                });
                if (selected) {
                    nativeSelect.val(String(selected));
                }
            };

            button.one('activate', async () => {
                const typed = String(input.val() || '').trim();
                const selectedValue = String(select.find('select').val() || '').trim();
                const selectedOption = folderOptions.find((option) => String(option.value) === selectedValue);
                const value = typed || (selectedOption ? String(selectedOption.label) : '');
                if (!value) {
                    Craft.cp.displayError(Craft.t('shortlink-manager', 'Folder name cannot be empty.'));
                    select.find('select').trigger('focus');
                    return;
                }

                button.addClass('loading');
                try {
                    const response = await Craft.sendActionRequest('POST', 'shortlink-manager/shortlinks/bulk-set-folder', {
                        data: { ids, folderName: value },
                    });
                    hud.hide();
                    Craft.cp.displayNotice(response.data?.message || Craft.t('shortlink-manager', 'Folder updated.'));
                    if (elementIndex && typeof elementIndex.updateElements === 'function') {
                        elementIndex.updateElements();
                    } else {
                        window.location.reload();
                    }
                } catch (e) {
                    const error = e?.response?.data?.error || Craft.t('shortlink-manager', 'Could not update folder.');
                    Craft.cp.displayError(error);
                } finally {
                    button.removeClass('loading');
                }
            });
        },
    });
})();
JS, [static::class, $folderOptions]);

        return null;
    }
}
