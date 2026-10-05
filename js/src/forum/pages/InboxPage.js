import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/helpers/humanTime';
import ComposePmModal from '../components/ComposePmModal';

function t(key, params) {
  return app.translator.trans('prm-messages.forum.' + key, params || {});
}

function otherUser(conversation) {
  if (!conversation || !app.session.user) {
    return null;
  }

  const me = app.session.user.id();
  const low = conversation.userLow && conversation.userLow();
  const high = conversation.userHigh && conversation.userHigh();

  if (low && low.id() === me) {
    return high || null;
  }

  return low || high || null;
}

export default class InboxPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    app.setTitle(t('title'));
    this.loading = true;
    this.conversations = [];
    this.total = 0;
    this.pageNumber = 1;
    this.perPage = 20;
    this.query = Stream('');
    this.searchTimer = null;

    if (!app.session.user) {
      m.route.set('/');
      return;
    }

    this.refresh();
  }

  view() {
    const pages = Math.max(1, Math.ceil(this.total / this.perPage));

    return (
      <div className="PmPage">
        <div className="container">
          <div className="PmPage-head">
            <h2>{t('title')}</h2>
            <div className="PmPage-actions">
              {app.forum.attribute('publicChatEnabled') ? (
                <LinkButton href={app.route('publicChat')} icon="fas fa-comments" className="Button">
                  {t('chat.header')}
                </LinkButton>
              ) : null}
              {app.forum.attribute('canStartPm') ? (
                <Button className="Button Button--primary" icon="fas fa-edit" onclick={() => app.modal.show(ComposePmModal)}>
                  {t('compose')}
                </Button>
              ) : null}
            </div>
          </div>

          <div className="PmPage-search">
            <input
              className="FormControl"
              placeholder={extractText(t('search_placeholder'))}
              value={this.query()}
              oninput={(e) => {
                this.query(e.target.value);
                if (this.searchTimer) {
                  clearTimeout(this.searchTimer);
                }
                this.searchTimer = setTimeout(() => {
                  this.pageNumber = 1;
                  this.refresh();
                }, 300);
              }}
            />
          </div>

          {this.loading ? (
            <LoadingIndicator />
          ) : this.conversations.length ? (
            [
              <div className="PmInbox">{this.conversations.map((conversation) => this.item(conversation))}</div>,
              pages > 1 ? (
                <div className="PmPage-pager">
                  {Array.from({ length: pages }, (_, i) => i + 1).map((page) => this.pageButton(page))}
                </div>
              ) : null,
            ]
          ) : (
            <p>{t('empty')}</p>
          )}
        </div>
      </div>
    );
  }

  pageButton(page) {
    return (
      <Button
        className={'Button' + (page === this.pageNumber ? ' Button--primary' : '')}
        onclick={() => {
          this.pageNumber = page;
          this.refresh();
        }}
      >
        {String(page)}
      </Button>
    );
  }

  item(conversation) {
    const user = otherUser(conversation);
    const unread = conversation.unreadCount() || 0;

    return (
      <Link
        className={'PmInbox-item' + (unread ? ' is-unread' : '')}
        href={app.route('messages.show', { id: conversation.id() })}
      >
        {user ? <Avatar user={user} /> : null}
        <div className="PmInbox-body">
          <div className="PmInbox-name">{user ? username(user) : ''}</div>
          {conversation.subject() ? <div className="PmInbox-subject">{conversation.subject()}</div> : null}
          <div className="PmInbox-preview">{conversation.preview() || ''}</div>
        </div>
        <div className="PmInbox-meta">
          {unread ? <div className="PmBadge">{String(unread)}</div> : null}
          <div>{humanTime(conversation.lastMessageAt() || conversation.createdAt())}</div>
        </div>
      </Link>
    );
  }

  refresh() {
    this.loading = true;

    const params = {
      page: { offset: (this.pageNumber - 1) * this.perPage, limit: this.perPage },
      include: 'userLow,userHigh,lastUser',
    };

    const q = (this.query() || '').trim();
    if (q) {
      params.filter = { q };
    }

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pm-conversations',
        params,
      })
      .then((payload) => {
        this.total =
          (payload.meta && payload.meta.page && payload.meta.page.total) ||
          (payload.meta && payload.meta.total) ||
          0;
        this.conversations = app.store.pushPayload(payload);
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
