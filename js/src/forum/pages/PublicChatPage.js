import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import username from 'flarum/common/helpers/username';
import humanTime from 'flarum/common/helpers/humanTime';

function t(key, params) {
  return app.translator.trans('prm-messages.forum.' + key, params || {});
}

export default class PublicChatPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    app.setTitle(t('chat.title'));
    this.loading = true;
    this.messages = [];
    this.message = Stream('');
    this.saving = false;
    this.poll = null;
    this.shouldScroll = true;

    if (!app.forum.attribute('publicChatEnabled')) {
      this.loading = false;
      return;
    }

    this.refresh();
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

  refresh(silent) {
    if (!silent) {
      this.loading = true;
    }

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pm-public-messages',
        params: { include: 'user' },
      })
      .then((payload) => {
        const items = app.store.pushPayload(payload) || [];
        this.messages = Array.isArray(items) ? items : [];
        this.loading = false;
        this.shouldScroll = !silent;

        if (!this.poll) {
          this.poll = setInterval(() => {
            this.refresh(true);
          }, 4000);
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
    if (!app.forum.attribute('publicChatEnabled')) {
      return (
        <div className="PmPage PublicChatPage">
          <div className="container">
            <p>{t('chat.disabled')}</p>
          </div>
        </div>
      );
    }

    const me = app.session.user && app.session.user.id();
    const canPost = app.forum.attribute('canPostPublicChat');

    return (
      <div className="PmPage PublicChatPage">
        <div className="container">
          <div className="PublicChatPage-head">
            <h2>{t('chat.title')}</h2>
            <p className="PublicChatPage-intro">{t('chat.intro')}</p>
          </div>

          {this.loading && !this.messages.length ? (
            <LoadingIndicator />
          ) : (
            <div className="PmThread">
              {this.messages.length
                ? this.messages.map((item) => {
                    const author = item.user && item.user();
                    const mine = author && author.id() === me;

                    return (
                      <div className={'PmBubble' + (mine ? ' PmBubble--own' : '')}>
                        <div className="PmBubble-meta">
                          {author ? username(author) : null}
                          <span>{humanTime(item.createdAt())}</span>
                          {item.canDelete() ? (
                            <button
                              className="PmBubble-delete"
                              type="button"
                              onclick={(e) => {
                                e.preventDefault();
                                this.remove(item);
                              }}
                            >
                              {t('chat.delete')}
                            </button>
                          ) : null}
                        </div>
                        <div className="PmBubble-body">{item.content()}</div>
                      </div>
                    );
                  })
                : (
                  <p>{t('chat.empty')}</p>
                )}
            </div>
          )}

          {canPost ? (
            <div className="PmComposer">
              <textarea
                className="FormControl"
                placeholder={extractText(t('chat.placeholder'))}
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
          ) : (
            <p>{t('chat.login')}</p>
          )}
        </div>
      </div>
    );
  }

  send() {
    const text = (this.message() || '').trim();

    if (!text || this.saving) {
      return;
    }

    this.saving = true;

    app.store
      .createRecord('pm-public-messages')
      .save({ content: text })
      .then(() => {
        this.message('');
        this.saving = false;
        this.refresh(true);
        this.shouldScroll = true;
      })
      .catch(() => {
        this.saving = false;
        m.redraw();
      });
  }

  remove(item) {
    item.delete().then(() => {
      this.messages = this.messages.filter((msg) => msg.id() !== item.id());
      m.redraw();
    });
  }
}
