import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import username from 'flarum/common/helpers/username';

function t(key, params) {
  return app.translator.trans('prm-messages.forum.' + key, params || {});
}

export default class ComposePmModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);

    this.query = Stream('');
    this.subject = Stream('');
    this.message = Stream('');
    this.results = [];
    this.selected = this.attrs.user || null;
    this.searching = false;
    this.searchTimer = null;
  }

  className() {
    return 'ComposePmModal Modal--small';
  }

  title() {
    return t('compose');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{t('to')}</label>
            {this.selected ? (
              <div className="ComposePmModal-selected">
                <Avatar user={this.selected} />
                {username(this.selected)}
                {this.attrs.user ? null : (
                  <Button className="Button Button--link" onclick={() => (this.selected = null)}>
                    ×
                  </Button>
                )}
              </div>
            ) : (
              <input
                className="FormControl"
                placeholder={extractText(t('search_placeholder'))}
                value={this.query()}
                oninput={(e) => {
                  this.query(e.target.value);
                  this.scheduleSearch();
                }}
              />
            )}
            {!this.selected && this.results.length ? (
              <ul className="ComposePmModal-results">
                {this.results.map((user) => (
                  <li>
                    <button
                      type="button"
                      onclick={() => {
                        this.selected = user;
                        this.results = [];
                      }}
                    >
                      <Avatar user={user} />
                      {username(user)}
                    </button>
                  </li>
                ))}
              </ul>
            ) : null}
            {!this.selected && this.query() && !this.searching && !this.results.length ? <p>{t('no_users')}</p> : null}
          </div>

          <div className="Form-group">
            <label>{t('subject')}</label>
            <input
              className="FormControl"
              placeholder={extractText(t('subject_placeholder'))}
              bidi={this.subject}
            />
          </div>

          <div className="Form-group">
            <label>{t('message')}</label>
            <textarea className="FormControl" bidi={this.message} />
          </div>

          <div className="Form-group">
            <Button className="Button Button--primary" type="submit" loading={this.loading}>
              {t('send')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  scheduleSearch() {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    this.searchTimer = setTimeout(() => this.searchUsers(), 250);
  }

  searchUsers() {
    const q = (this.query() || '').trim();

    if (q.length < 2) {
      this.results = [];
      m.redraw();
      return;
    }

    this.searching = true;

    app.store
      .find('users', { filter: { q }, page: { limit: 6 } })
      .then((users) => {
        const me = app.session.user && app.session.user.id();
        this.results = (users || []).filter((user) => user.id() !== me);
        this.searching = false;
        m.redraw();
      })
      .catch(() => {
        this.searching = false;
        m.redraw();
      });
  }

  onsubmit(e) {
    e.preventDefault();

    if (!this.selected || !(this.message() || '').trim()) {
      return;
    }

    this.loading = true;

    app.store
      .createRecord('pm-conversations')
      .save(
        {
          recipientId: this.selected.id(),
          subject: (this.subject() || '').trim(),
          content: this.message().trim(),
        },
        { include: 'userLow,userHigh,lastUser,messages,messages.user' }
      )
      .then((conversation) => {
        app.alerts.show({ type: 'success' }, t('sent'));
        this.hide();
        m.route.set(app.route('messages.show', { id: conversation.id() }));
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
