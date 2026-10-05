import Model from 'flarum/common/Model';

export default class PublicMessage extends Model {}

Object.assign(PublicMessage.prototype, {
  content: Model.attribute('content'),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  canDelete: Model.attribute('canDelete'),
  user: Model.hasOne('user'),
});
