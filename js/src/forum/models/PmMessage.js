import Model from 'flarum/common/Model';

export default class PmMessage extends Model {}

Object.assign(PmMessage.prototype, {
  content: Model.attribute('content'),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  user: Model.hasOne('user'),
  conversation: Model.hasOne('conversation'),
});
