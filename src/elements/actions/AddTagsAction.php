<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\elements\actions;

use Craft;
use craft\base\ElementAction;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Bulk element action: add tags to the selected short links.
 *
 * @since 5.15.0
 */
class AddTagsAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('shortlink-manager', 'Add Tags...');
    }

    public function getTriggerHtml(): ?string
    {
        $existingTags = ShortLinkManager::$plugin->taxonomy->getAllTagNames();

        Craft::$app->getView()->registerJsWithVars(fn($type, $tagsData) => <<<JS
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
            const existingTags = $tagsData;

            const existingWrap = $('<div/>');
            const select = $('<select/>', {
                class: 'text fullwidth',
                multiple: true,
                size: 8,
            }).appendTo(existingWrap);
            existingTags.forEach((tag) => {
                $('<option/>', { value: tag, text: tag }).appendTo(select);
            });
            Craft.ui.createField(existingWrap, {
                label: Craft.t('shortlink-manager', 'Existing tags'),
            }).appendTo(container);

            const inputWrap = $('<div/>');
            const input = $('<input/>', {
                class: 'text fullwidth',
                type: 'text',
                placeholder: Craft.t('shortlink-manager', 'Enter tags (comma-separated)'),
            }).appendTo(inputWrap);
            Craft.ui.createField(inputWrap, {
                label: Craft.t('shortlink-manager', 'Additional tags (optional)'),
                instructions: Craft.t('shortlink-manager', 'Comma-separated tags (e.g., campaign, spring, paid).'),
            }).appendTo(container);

            const buttonRow = $('<div/>', { class: 'buttons right' }).appendTo(container);
            const button = Craft.ui.createSubmitButton({
                label: Craft.t('app', 'Save'),
                spinner: true,
            }).appendTo(buttonRow);
            const hud = new Garnish.HUD(elementIndex.\$actionMenuBtn, container);
            setTimeout(() => input.trigger('focus'), 50);
            let requestInFlight = false;

            button.on('activate', async () => {
                const selectedTags = (select.val() || []).map((tag) => String(tag).trim()).filter(Boolean);
                const typedValue = String(input.val() || '').trim();
                const typedTags = typedValue
                    ? typedValue.split(',').map((tag) => String(tag).trim()).filter(Boolean)
                    : [];
                const allTags = [...new Set([...selectedTags, ...typedTags])];
                if (!allTags.length) {
                    Craft.cp.displayError(Craft.t('shortlink-manager', 'Tags cannot be empty.'));
                    select.trigger('focus');
                    return;
                }

                if (requestInFlight) {
                    return;
                }

                requestInFlight = true;
                button.addClass('loading');
                try {
                    const response = await Craft.sendActionRequest('POST', 'shortlink-manager/shortlinks/bulk-add-tags', {
                        data: { ids, tags: allTags.join(',') },
                    });
                    hud.hide();
                    Craft.cp.displayNotice(response.data?.message || Craft.t('shortlink-manager', 'Tags updated.'));
                    if (elementIndex && typeof elementIndex.updateElements === 'function') {
                        elementIndex.updateElements();
                    } else {
                        window.location.reload();
                    }
                } catch (e) {
                    const error = e?.response?.data?.error || Craft.t('shortlink-manager', 'Could not update tags.');
                    Craft.cp.displayError(error);
                } finally {
                    requestInFlight = false;
                    button.removeClass('loading');
                }
            });
        },
    });
})();
JS, [static::class, $existingTags]);

        return null;
    }
}
