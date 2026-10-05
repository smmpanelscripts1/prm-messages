import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import UserControls from 'flarum/forum/utils/UserControls';
import ComposePmModal from './components/ComposePmModal';

export { default as extend } from './extend';

function t(key, params) {
  return app.translator.trans('prm-messages.forum.' + key, params || {});
}

function openPm(user) {
  if (!user) {
    return;
  }
  app.modal.show(ComposePmModal, { user });
}

app.initializers.add('prm-messages', () => {
  extend('flarum/forum/components/HeaderSecondary', 'items', function (items) {
    if (app.forum.attribute('publicChatEnabled')) {
      items.add(
        'public-chat',
        <LinkButton href={app.route('publicChat')} icon="fas fa-comments" className="Button Button--link">
          {t('chat.header')}
        </LinkButton>,
        15
      );
    }

    if (!app.session.user || !app.forum.attribute('canStartPm')) {
      return;
    }

    const count = app.forum.attribute('unreadPmCount') || 0;

    items.add(
      'messages',
      <LinkButton href={app.route('messages')} icon="fas fa-envelope" className="Button Button--link">
        {t('header')}
        {count ? <span className="PmBadge">{String(count)}</span> : null}
      </LinkButton>,
      14
    );
  });

  extend(UserControls, 'userControls', function (items, user) {
    if (user && user.canReceivePm && user.canReceivePm()) {
      items.add(
        'pm-message',
        <Button icon="fas fa-envelope" onclick={() => openPm(user)}>
          {t('user_controls.message')}
        </Button>,
        80
      );
    }
  });

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items) {
    items.add('pmMessageReceived', {
      name: 'pmMessageReceived',
      icon: 'fas fa-envelope',
      label: t('notifications.notify_label'),
    });
  });
});
