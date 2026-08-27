<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\elements\actions;

use Craft;
use craft\base\ElementAction;

/**
 * Bulk element action: remove the selected short links from their folder.
 *
 * @since 5.15.0
 */
class ClearFolderAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('shortlink-manager', 'Clear Folder');
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: true,
        validateSelection: (selectedItems, elementIndex) => {
            for (let i = 0; i < selectedItems.length; i++) {
                const element = selectedItems.eq(i).find('.element');
                if (!Garnish.hasAttr(element, 'data-savable')) {
                    return false;
                }
            }
            return true;
        },
        activate: async (selectedItems, elementIndex) => {
            const ids = (typeof elementIndex.getSelectedElementIds === 'function'
                ? elementIndex.getSelectedElementIds()
                : []
            ).map((id) => Number(id)).filter((id) => id > 0);

            if (!ids.length) {
                return;
            }

            const container = $('<div/>');
            $('<p/>', {
                text: Craft.t('shortlink-manager', 'Clear folder for selected shortlinks?'),
                class: 'light',
                style: 'margin:0 0 12px',
            }).appendTo(container);
            const buttonRow = $('<div/>', { class: 'buttons right' }).appendTo(container);
            const button = Craft.ui.createSubmitButton({
                label: Craft.t('shortlink-manager', 'Clear Folder'),
                spinner: true,
            }).appendTo(buttonRow);
            const hud = new Garnish.HUD(elementIndex.\$actionMenuBtn, container);
            let requestInFlight = false;

            button.on('activate', async () => {
                if (requestInFlight) {
                    return;
                }

                requestInFlight = true;
                button.addClass('loading');
                try {
                    const response = await Craft.sendActionRequest('POST', 'shortlink-manager/shortlinks/bulk-clear-folder', {
                        data: { ids },
                    });
                    hud.hide();
                    Craft.cp.displayNotice(response.data?.message || Craft.t('shortlink-manager', 'Folder cleared.'));
                    if (elementIndex && typeof elementIndex.updateElements === 'function') {
                        elementIndex.updateElements();
                    } else {
                        window.location.reload();
                    }
                } catch (e) {
                    const error = e?.response?.data?.error || Craft.t('shortlink-manager', 'Could not clear folder.');
                    Craft.cp.displayError(error);
                } finally {
                    requestInFlight = false;
                    button.removeClass('loading');
                }
            });
        },
    });
})();
JS, [static::class]);

        return null;
    }
}
