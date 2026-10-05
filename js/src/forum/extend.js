import Extend from 'flarum/common/extenders';
import User from 'flarum/common/models/User';
import PmConversation from './models/PmConversation';
import PmMessage from './models/PmMessage';
import PublicMessage from './models/PublicMessage';
import InboxPage from './pages/InboxPage';
import ConversationPage from './pages/ConversationPage';
import PublicChatPage from './pages/PublicChatPage';
import PmReceivedNotification from './components/PmReceivedNotification';

export default [
  new Extend.Store()
    .add('pm-conversations', PmConversation)
    .add('pm-messages', PmMessage)
    .add('pm-public-messages', PublicMessage),

  new Extend.Model(User).attribute('canReceivePm'),

  new Extend.Routes()
    .add('messages', '/messages', InboxPage)
    .add('messages.show', '/messages/:id', ConversationPage)
    .add('publicChat', '/chat', PublicChatPage),

  new Extend.Notification().add('pmMessageReceived', PmReceivedNotification),
];
