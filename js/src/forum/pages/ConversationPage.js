import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/helpers/humanTime';

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

function bumpUnread(delta) {
  const current = app.forum.attribute('unreadPmCount') || 0;
  app.forum.data.attributes.unreadPmCount = Math.max(0, current + delta);
}

export default class ConversationPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    this.conversation = null;
    this.loading = true;
    this.message = Stream('');
    this.saving = false;
    this.poll = null;
    this.shouldScroll = false;
    this.load();
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    this.scrollToEnd();
  }

  onupdate() {
    if (this.shouldScroll) {
      this.shouldScroll = false;
      this.scrollToEnd();
    }
  }

  onremove(vnode) {
    if (this.poll) {
      clearInterval(this.poll);
      this.poll = null;
    }
    if (Page.prototype.onremove) {
      Page.prototype.onremove.call(this, vnode);
    }
  }

  load(silent) {
    const id = m.route.param('id');

    if (!silent) {
      this.loading = true;
    }

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pm-conversations/' + id,
        params: { include: 'userLow,userHigh,lastUser,messages,messages.user' },
      })
      .then((payload) => {
        const cleared = (payload.meta && payload.meta.clearedUnread) || 0;
        if (cleared) {
          bumpUnread(-cleared);
        }

        this.conversation = app.store.pushPayload(payload);
        this.loading = false;
        this.shouldScroll = !silent;

        const other = otherUser(this.conversation);
        app.setTitle(other ? other.displayName() : t('title'));

        if (!this.poll) {
          this.poll = setInterval(() => {
            this.load(true);
          }, 6000);
        }

        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  scrollToEnd() {
    const el = this.element && this.element.querySelector('.PmThread');
    if (el) {
      el.scrollTop = el.scrollHeight;
    }
  }

  view() {
    if (this.loading || !this.conversation) {
      return (
        <div className="PmPage">
          <div className="container">{this.loading ? <LoadingIndicator /> : <p>{t('empty')}</p>}</div>
        </div>
      );
    }

    const conversation = this.conversation;
    const user = otherUser(conversation);
    const messages = (conversation.messages && conversation.messages()) || [];
    const me = app.session.user && app.session.user.id();

    return (
      <div className="PmPage PmThreadPage">
        <div className="container">
          <Link href={app.route('messages')} className="PmThreadPage-back">
            ← {extractText(t('back'))}
          </Link>

          <div className="PmThreadPage-head">
            <div className="PmThreadPage-user">
              {user ? <Avatar user={user} /> : null}
              <div>
                <h2>{user ? username(user) : ''}</h2>
                {conversation.subject() ? <div className="PmThreadPage-subject">{conversation.subject()}</div> : null}
              </div>
            </div>
            {conversation.canHide() ? (
              <Button
                className="Button"
                icon="fas fa-eye-slash"
                onclick={() => {
                  if (!window.confirm(extractText(t('hide_confirm')))) {
                    return;
                  }
                  conversation.save({ hidden: true }).then(() => {
                    m.route.set(app.route('messages'));
                  });
                }}
              >
                {t('hide')}
              </Button>
            ) : null}
          </div>

          <div className="PmThread">
            {messages.map((item) => {
              const author = item.user && item.user();
              const mine = author && author.id() === me;

              return (
                <div className={'PmBubble' + (mine ? ' PmBubble--own' : '')}>
                  <div className="PmBubble-meta">
                    {author ? username(author) : null}
                    <span>{humanTime(item.createdAt())}</span>
                  </div>
                  <div className="PmBubble-body">{item.content()}</div>
                </div>
              );
            })}
          </div>

          {conversation.canReply() ? (
            <div className="PmComposer">
              <textarea
                className="FormControl"
                placeholder={extractText(t('type_placeholder'))}
                value={this.message()}
                oninput={(e) => this.message(e.target.value)}
                onkeydown={(e) => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.send();
                  }
                }}
              />
              <div className="PmComposer-actions">
                <Button className="Button Button--primary" loading={this.saving} onclick={() => this.send()}>
                  {t('send')}
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      </div>
    );
  }

  send() {
    const text = (this.message() || '').trim();

    if (!text || this.saving || !this.conversation) {
      return;
    }

    this.saving = true;

    app.store
      .createRecord('pm-messages')
      .save({
        content: text,
        relationships: { conversation: this.conversation },
      })
      .then(() => {
        this.message('');
        this.saving = false;
        this.load(true);
        this.shouldScroll = true;
      })
      .catch(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
