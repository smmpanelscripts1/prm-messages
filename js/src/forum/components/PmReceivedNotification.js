import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class PmReceivedNotification extends Notification {
  icon() {
    return 'fas fa-envelope';
  }

  href() {
    const subject = this.attrs.notification.subject && this.attrs.notification.subject();

    if (subject && subject.id) {
      return app.route('messages.show', { id: subject.id() });
    }

    const data = this.attrs.notification.content() || {};

    return data.conversationId
      ? app.route('messages.show', { id: data.conversationId })
      : app.forum.attribute('baseUrl');
  }

  content() {
    const fromUser = this.attrs.notification.fromUser();

    return app.translator.trans('prm-messages.forum.notifications.received', {
      username: fromUser ? fromUser.displayName() : '',
    });
  }
}
