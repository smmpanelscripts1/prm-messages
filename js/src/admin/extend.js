import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

export default [
  new Extend.Admin()
    .permission(
      () => ({
        icon: 'fas fa-envelope',
        label: app.translator.trans('prm-messages.admin.permissions.start_label'),
        permission: 'pm.start',
      }),
      'start'
    )
    .setting(() => ({
      setting: 'prm-messages.public_chat',
      type: 'boolean',
      label: app.translator.trans('prm-messages.admin.settings.public_chat_label'),
      help: app.translator.trans('prm-messages.admin.settings.public_chat_help'),
    })),
];
