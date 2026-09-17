(function () {
var app = flarum.core.compat['forum/app'] || flarum.core.compat.app;
var extendMod = flarum.core.compat['common/extend'] || {};
var extend = extendMod.extend;
var Model = flarum.core.compat['common/Model'];
var User = flarum.core.compat['common/models/User'];
var Page = flarum.core.compat['common/components/Page'];
var LinkButton = flarum.core.compat['common/components/LinkButton'];
var Button = flarum.core.compat['common/components/Button'];
var Link = flarum.core.compat['common/components/Link'];
var Modal = flarum.core.compat['common/components/Modal'];
var LoadingIndicator = flarum.core.compat['common/components/LoadingIndicator'];
var HeaderSecondary = flarum.core.compat['forum/components/HeaderSecondary'];
var UserControls = flarum.core.compat['forum/utils/UserControls'];
var NotificationGrid = flarum.core.compat['forum/components/NotificationGrid'];
var Notification = flarum.core.compat['forum/components/Notification'];
var Stream = flarum.core.compat['common/utils/Stream'];
var username = flarum.core.compat['common/helpers/username'];
var extractText = flarum.core.compat['common/utils/extractText'];
var humanTime = flarum.core.compat['common/helpers/humanTime'];
var avatar = flarum.core.compat['common/helpers/avatar'];
var m = window.m;

function t(key, params) {
  return app.translator.trans('prm-messages.forum.' + key, params || {});
}

function otherUser(conversation) {
  if (!conversation || !app.session.user) {
    return null;
  }
  var me = app.session.user.id();
  var low = conversation.userLow && conversation.userLow();
  var high = conversation.userHigh && conversation.userHigh();
  if (low && low.id() === me) {
    return high || null;
  }
  return low || high || null;
}

function bumpUnread(delta) {
  var current = app.forum.attribute('unreadPmCount') || 0;
  app.forum.data.attributes.unreadPmCount = Math.max(0, current + delta);
}

class PmConversation extends Model {}
PmConversation.prototype.preview = Model.attribute('preview');
PmConversation.prototype.subject = Model.attribute('subject');
PmConversation.prototype.unreadCount = Model.attribute('unreadCount');
PmConversation.prototype.hidden = Model.attribute('hidden');
PmConversation.prototype.createdAt = Model.attribute('createdAt', Model.transformDate);
PmConversation.prototype.lastMessageAt = Model.attribute('lastMessageAt', Model.transformDate);
PmConversation.prototype.canReply = Model.attribute('canReply');
PmConversation.prototype.canHide = Model.attribute('canHide');
PmConversation.prototype.userLow = Model.hasOne('userLow');
PmConversation.prototype.userHigh = Model.hasOne('userHigh');
PmConversation.prototype.lastUser = Model.hasOne('lastUser');
PmConversation.prototype.messages = Model.hasMany('messages');

class PmMessage extends Model {}
PmMessage.prototype.content = Model.attribute('content');
PmMessage.prototype.createdAt = Model.attribute('createdAt', Model.transformDate);
PmMessage.prototype.user = Model.hasOne('user');
PmMessage.prototype.conversation = Model.hasOne('conversation');

class PmReceivedNotification extends Notification {
  icon() {
    return 'fas fa-envelope';
  }

  href() {
    var subject = this.attrs.notification.subject && this.attrs.notification.subject();
    if (subject && subject.id) {
      return app.route('messages.show', { id: subject.id() });
    }
    var data = this.attrs.notification.content() || {};
    return data.conversationId ? app.route('messages.show', { id: data.conversationId }) : app.forum.attribute('baseUrl');
  }

  content() {
    var fromUser = this.attrs.notification.fromUser();
    return t('notifications.received', {
      username: fromUser ? fromUser.displayName() : '',
    });
  }
}

class ComposePmModal extends Modal {
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
    return 'ComposePmModal';
  }

  title() {
    return t('compose');
  }

  content() {
    var self = this;
    return m('div.Modal-body', m('div.Form', [
      m('div.Form-group', [
        m('label', t('to')),
        this.selected
          ? m('div.ComposePmModal-selected', [
              avatar(this.selected),
              username(this.selected),
              this.attrs.user
                ? null
                : m(
                    Button,
                    {
                      className: 'Button Button--link',
                      onclick: function () {
                        self.selected = null;
                      },
                    },
                    '×'
                  ),
            ])
          : m('input.FormControl', {
              placeholder: extractText(t('search_placeholder')),
              value: this.query(),
              oninput: function (e) {
                self.query(e.target.value);
                self.scheduleSearch();
              },
            }),
        !this.selected && this.results.length
          ? m(
              'ul.ComposePmModal-results',
              this.results.map(function (user) {
                return m('li', m(
                  'button',
                  {
                    type: 'button',
                    onclick: function () {
                      self.selected = user;
                      self.results = [];
                    },
                  },
                  [avatar(user), username(user)]
                ));
              })
            )
          : null,
        !this.selected && this.query() && !this.searching && !this.results.length
          ? m('p', t('no_users'))
          : null,
      ]),
      m('div.Form-group', [
        m('label', t('subject')),
        m('input.FormControl', {
          placeholder: extractText(t('subject_placeholder')),
          value: this.subject(),
          oninput: function (e) {
            self.subject(e.target.value);
          },
        }),
      ]),
      m('div.Form-group', [
        m('label', t('message')),
        m('textarea.FormControl', {
          value: this.message(),
          oninput: function (e) {
            self.message(e.target.value);
          },
        }),
      ]),
      m(
        'div.Form-group',
        m(Button, { className: 'Button Button--primary', type: 'submit', loading: this.loading }, t('send'))
      ),
    ]));
  }

  scheduleSearch() {
    var self = this;
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    this.searchTimer = setTimeout(function () {
      self.searchUsers();
    }, 250);
  }

  searchUsers() {
    var self = this;
    var q = (this.query() || '').trim();
    if (q.length < 2) {
      this.results = [];
      m.redraw();
      return;
    }
    this.searching = true;
    app.store
      .find('users', { filter: { q: q }, page: { limit: 6 } })
      .then(function (users) {
        var me = app.session.user && app.session.user.id();
        self.results = (users || []).filter(function (user) {
          return user.id() !== me;
        });
        self.searching = false;
        m.redraw();
      })
      .catch(function () {
        self.searching = false;
        m.redraw();
      });
  }

  onsubmit(e) {
    e.preventDefault();
    if (!this.selected || !(this.message() || '').trim()) {
      return;
    }
    var self = this;
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
      .then(function (conversation) {
        app.alerts.show({ type: 'success' }, t('sent'));
        self.hide();
        m.route.set(app.route('messages.show', { id: conversation.id() }));
      })
      .catch(function () {
        self.loading = false;
        m.redraw();
      });
  }
}

function openPm(user) {
  if (!user) {
    return;
  }
  app.modal.show(ComposePmModal, { user: user });
}

class InboxPage extends Page {
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
    var self = this;
    var pages = Math.max(1, Math.ceil(this.total / this.perPage));
    var pager = [];
    var i;
    for (i = 1; i <= pages; i++) {
      pager.push(this.pageButton(i));
    }

    return m('div.PmPage', m('div.container', [
      m('div.PmPage-head', [
        m('h2', t('title')),
        m('div.PmPage-actions', [
          app.forum.attribute('publicChatEnabled')
            ? m(LinkButton, { href: app.route('publicChat'), icon: 'fas fa-comments', className: 'Button' }, t('chat.header'))
            : null,
          app.forum.attribute('canStartPm')
            ? m(
                Button,
                {
                  className: 'Button Button--primary',
                  icon: 'fas fa-edit',
                  onclick: function () {
                    app.modal.show(ComposePmModal);
                  },
                },
                t('compose')
              )
            : null,
        ]),
      ]),
      m('div.PmPage-search', m('input.FormControl', {
        placeholder: extractText(t('search_placeholder')),
        value: this.query(),
        oninput: function (e) {
          self.query(e.target.value);
          if (self.searchTimer) {
            clearTimeout(self.searchTimer);
          }
          self.searchTimer = setTimeout(function () {
            self.pageNumber = 1;
            self.refresh();
          }, 300);
        },
      })),
      this.loading
        ? m(LoadingIndicator)
        : this.conversations.length
          ? [
              m(
                'div.PmInbox',
                this.conversations.map(function (conversation) {
                  return self.item(conversation);
                })
              ),
              pages > 1 ? m('div.PmPage-pager', pager) : null,
            ]
          : m('p', t('empty')),
    ]));
  }

  pageButton(page) {
    var self = this;
    return m(
      Button,
      {
        className: 'Button' + (page === this.pageNumber ? ' Button--primary' : ''),
        onclick: function () {
          self.pageNumber = page;
          self.refresh();
        },
      },
      String(page)
    );
  }

  item(conversation) {
    var user = otherUser(conversation);
    var unread = conversation.unreadCount() || 0;
    return m(
      Link,
      {
        className: 'PmInbox-item' + (unread ? ' is-unread' : ''),
        href: app.route('messages.show', { id: conversation.id() }),
      },
      [
        user ? avatar(user) : null,
        m('div.PmInbox-body', [
          m('div.PmInbox-name', user ? username(user) : ''),
          conversation.subject()
            ? m('div.PmInbox-subject', conversation.subject())
            : null,
          m('div.PmInbox-preview', conversation.preview() || ''),
        ]),
        m('div.PmInbox-meta', [
          unread ? m('div.PmBadge', String(unread)) : null,
          m('div', humanTime(conversation.lastMessageAt() || conversation.createdAt())),
        ]),
      ]
    );
  }

  refresh() {
    var self = this;
    this.loading = true;
    var params = {
      page: { offset: (this.pageNumber - 1) * this.perPage, limit: this.perPage },
      include: 'userLow,userHigh,lastUser',
    };
    var q = (this.query() || '').trim();
    if (q) {
      params.filter = { q: q };
    }
    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pm-conversations',
        params: params,
      })
      .then(function (payload) {
        self.total = (payload.meta && payload.meta.total) || 0;
        self.conversations = app.store.pushPayload(payload);
        self.loading = false;
        m.redraw();
      })
      .catch(function () {
        self.loading = false;
        m.redraw();
      });
  }
}

class ConversationPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    this.conversation = null;
    this.loading = true;
    this.message = Stream('');
    this.saving = false;
    this.poll = null;
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
    var self = this;
    var id = m.route.param('id');
    if (!silent) {
      this.loading = true;
    }
    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pm-conversations/' + id,
        params: { include: 'userLow,userHigh,lastUser,messages,messages.user' },
      })
      .then(function (payload) {
        var cleared = (payload.meta && payload.meta.clearedUnread) || 0;
        if (cleared) {
          bumpUnread(-cleared);
        }
        self.conversation = app.store.pushPayload(payload);
        self.loading = false;
        self.shouldScroll = !silent;
        app.setTitle(otherUser(self.conversation) ? otherUser(self.conversation).displayName() : t('title'));
        if (!self.poll) {
          self.poll = setInterval(function () {
            self.load(true);
          }, 6000);
        }
        m.redraw();
      })
      .catch(function () {
        self.loading = false;
        m.redraw();
      });
  }

  scrollToEnd() {
    var el = this.element && this.element.querySelector('.PmThread');
    if (el) {
      el.scrollTop = el.scrollHeight;
    }
  }

  view() {
    var self = this;
    if (this.loading || !this.conversation) {
      return m('div.PmPage', m('div.container', this.loading ? m(LoadingIndicator) : m('p', t('empty'))));
    }

    var conversation = this.conversation;
    var user = otherUser(conversation);
    var messages = (conversation.messages && conversation.messages()) || [];
    var me = app.session.user && app.session.user.id();

    return m('div.PmPage.PmThreadPage', m('div.container', [
      m(Link, { href: app.route('messages'), className: 'PmThreadPage-back' }, '← ' + extractText(t('back'))),
      m('div.PmThreadPage-head', [
        m('div.PmThreadPage-user', [
          user ? avatar(user) : null,
          m('div', [
            m('h2', user ? username(user) : ''),
            conversation.subject() ? m('div.PmThreadPage-subject', conversation.subject()) : null,
          ]),
        ]),
        conversation.canHide()
          ? m(
              Button,
              {
                className: 'Button',
                icon: 'fas fa-eye-slash',
                onclick: function () {
                  if (!window.confirm(extractText(t('hide_confirm')))) {
                    return;
                  }
                  conversation.save({ hidden: true }).then(function () {
                    m.route.set(app.route('messages'));
                  });
                },
              },
              t('hide')
            )
          : null,
      ]),
      m(
        'div.PmThread',
        messages.map(function (item) {
          var author = item.user && item.user();
          var mine = author && author.id() === me;
          return m('div.PmBubble' + (mine ? '.PmBubble--own' : ''), [
            m('div.PmBubble-meta', [
              author ? username(author) : null,
              m('span', humanTime(item.createdAt())),
            ]),
            m('div.PmBubble-body', item.content()),
          ]);
        })
      ),
      conversation.canReply()
        ? m('div.PmComposer', [
            m('textarea.FormControl', {
              placeholder: extractText(t('type_placeholder')),
              value: this.message(),
              oninput: function (e) {
                self.message(e.target.value);
              },
              onkeydown: function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault();
                  self.send();
                }
              },
            }),
            m(
              'div.PmComposer-actions',
              m(
                Button,
                {
                  className: 'Button Button--primary',
                  loading: this.saving,
                  onclick: function () {
                    self.send();
                  },
                },
                t('send')
              )
            ),
          ])
        : null,
    ]));
  }

  send() {
    var self = this;
    var text = (this.message() || '').trim();
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
      .then(function () {
        self.message('');
        self.saving = false;
        self.load(true);
        self.shouldScroll = true;
      })
      .catch(function () {
        self.saving = false;
        m.redraw();
      });
  }
}

class PublicMessage extends Model {}
PublicMessage.prototype.content = Model.attribute('content');
PublicMessage.prototype.createdAt = Model.attribute('createdAt', Model.transformDate);
PublicMessage.prototype.canDelete = Model.attribute('canDelete');
PublicMessage.prototype.user = Model.hasOne('user');

class PublicChatPage extends Page {
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
    var self = this;
    if (!silent) {
      this.loading = true;
    }
    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pm-public-messages',
        params: { include: 'user' },
      })
      .then(function (payload) {
        var items = app.store.pushPayload(payload) || [];
        self.messages = Array.isArray(items) ? items : [];
        self.loading = false;
        self.shouldScroll = !silent;
        if (!self.poll) {
          self.poll = setInterval(function () {
            self.refresh(true);
          }, 4000);
        }
        m.redraw();
      })
      .catch(function () {
        self.loading = false;
        m.redraw();
      });
  }

  scrollToEnd() {
    var el = this.element && this.element.querySelector('.PmThread');
    if (el) {
      el.scrollTop = el.scrollHeight;
    }
  }

  view() {
    var self = this;
    if (!app.forum.attribute('publicChatEnabled')) {
      return m('div.PmPage.PublicChatPage', m('div.container', m('p', t('chat.disabled'))));
    }

    var me = app.session.user && app.session.user.id();
    var canPost = app.forum.attribute('canPostPublicChat');

    return m('div.PmPage.PublicChatPage', m('div.container', [
      m('div.PublicChatPage-head', [
        m('h2', t('chat.title')),
        m('p.PublicChatPage-intro', t('chat.intro')),
      ]),
      this.loading && !this.messages.length
        ? m(LoadingIndicator)
        : m(
            'div.PmThread',
            this.messages.length
              ? this.messages.map(function (item) {
                  var author = item.user && item.user();
                  var mine = author && author.id() === me;
                  return m('div.PmBubble' + (mine ? '.PmBubble--own' : ''), [
                    m('div.PmBubble-meta', [
                      author ? username(author) : null,
                      m('span', humanTime(item.createdAt())),
                      item.canDelete()
                        ? m(
                            'button.PmBubble-delete',
                            {
                              type: 'button',
                              onclick: function (e) {
                                e.preventDefault();
                                self.remove(item);
                              },
                            },
                            t('chat.delete')
                          )
                        : null,
                    ]),
                    m('div.PmBubble-body', item.content()),
                  ]);
                })
              : m('p', t('chat.empty'))
          ),
      canPost
        ? m('div.PmComposer', [
            m('textarea.FormControl', {
              placeholder: extractText(t('chat.placeholder')),
              value: this.message(),
              oninput: function (e) {
                self.message(e.target.value);
              },
              onkeydown: function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault();
                  self.send();
                }
              },
            }),
            m(
              'div.PmComposer-actions',
              m(
                Button,
                {
                  className: 'Button Button--primary',
                  loading: this.saving,
                  onclick: function () {
                    self.send();
                  },
                },
                t('send')
              )
            ),
          ])
        : m('p', t('chat.login')),
    ]));
  }

  send() {
    var self = this;
    var text = (this.message() || '').trim();
    if (!text || this.saving) {
      return;
    }
    this.saving = true;
    app.store
      .createRecord('pm-public-messages')
      .save({ content: text })
      .then(function () {
        self.message('');
        self.saving = false;
        self.refresh(true);
        self.shouldScroll = true;
      })
      .catch(function () {
        self.saving = false;
        m.redraw();
      });
  }

  remove(item) {
    var self = this;
    item.delete().then(function () {
      self.messages = self.messages.filter(function (msg) {
        return msg.id() !== item.id();
      });
      m.redraw();
    });
  }
}

app.initializers.add('prm-messages', function () {
  app.store.models['pm-conversations'] = PmConversation;
  app.store.models['pm-messages'] = PmMessage;
  app.store.models['pm-public-messages'] = PublicMessage;
  app.routes.messages = { path: '/messages', component: InboxPage };
  app.routes['messages.show'] = { path: '/messages/:id', component: ConversationPage };
  app.routes.publicChat = { path: '/chat', component: PublicChatPage };
  app.notificationComponents.pmMessageReceived = PmReceivedNotification;

  User.prototype.canReceivePm = Model.attribute('canReceivePm');

  extend(HeaderSecondary.prototype, 'items', function (items) {
    if (app.forum.attribute('publicChatEnabled')) {
      items.add(
        'public-chat',
        m(LinkButton, { href: app.route('publicChat'), icon: 'fas fa-comments', className: 'Button Button--link' }, t('chat.header')),
        15
      );
    }
    if (!app.session.user || !app.forum.attribute('canStartPm')) {
      return;
    }
    var count = app.forum.attribute('unreadPmCount') || 0;
    items.add(
      'messages',
      m(
        LinkButton,
        { href: app.route('messages'), icon: 'fas fa-envelope', className: 'Button Button--link' },
        [t('header'), count ? m('span.PmBadge', String(count)) : null]
      ),
      14
    );
  });

  extend(UserControls, 'userControls', function (items, user) {
    if (user && user.canReceivePm && user.canReceivePm()) {
      items.add(
        'pm-message',
        m(
          Button,
          {
            icon: 'fas fa-envelope',
            onclick: function () {
              openPm(user);
            },
          },
          t('user_controls.message')
        ),
        80
      );
    }
  });

  extend(NotificationGrid.prototype, 'notificationTypes', function (items) {
    items.add('pmMessageReceived', {
      name: 'pmMessageReceived',
      icon: 'fas fa-envelope',
      label: t('notifications.notify_label'),
    });
  });
});

module.exports = {};
})();
