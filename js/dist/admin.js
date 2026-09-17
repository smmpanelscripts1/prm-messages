(function () {
var app = flarum.core.compat['admin/app'] || flarum.core.compat.app;

app.initializers.add('prm-messages', function () {
  app.extensionData
    .for('prm-messages')
    .registerSetting({
      setting: 'prm-messages.public_chat',
      type: 'boolean',
      label: app.translator.trans('prm-messages.admin.settings.public_chat_label'),
      help: app.translator.trans('prm-messages.admin.settings.public_chat_help'),
    })
    .registerPermission(
      {
        icon: 'fas fa-envelope',
        label: app.translator.trans('prm-messages.admin.permissions.start_label'),
        permission: 'pm.start',
      },
      'start'
    );
});

module.exports = {};
})();
