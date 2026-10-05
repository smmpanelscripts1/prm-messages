import Model from 'flarum/common/Model';

export default class PmConversation extends Model {}

Object.assign(PmConversation.prototype, {
  preview: Model.attribute('preview'),
  subject: Model.attribute('subject'),
  unreadCount: Model.attribute('unreadCount'),
  hidden: Model.attribute('hidden'),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  lastMessageAt: Model.attribute('lastMessageAt', Model.transformDate),
  canReply: Model.attribute('canReply'),
  canHide: Model.attribute('canHide'),
  userLow: Model.hasOne('userLow'),
  userHigh: Model.hasOne('userHigh'),
  lastUser: Model.hasOne('lastUser'),
  messages: Model.hasMany('messages'),
});
