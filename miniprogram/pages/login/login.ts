import { api } from '../../utils/api';

Page({
  data: {
    loading: false,
    helpVisible: false,
  },
  onWechatLogin() {
    if (this.data.loading) {
      return;
    }
    this.setData({ loading: true });
    wx.login({
      success: async (result: any) => {
        try {
          await api.login(result.code);
          wx.switchTab({ url: '/pages/home/home' });
        } catch (error: any) {
          wx.showToast({ title: error.message, icon: 'none' });
        } finally {
          this.setData({ loading: false });
        }
      },
      fail: () => {
        this.setData({ loading: false });
        wx.showToast({ title: '微信登录失败', icon: 'none' });
      },
    });
  },
  showHelpCenter() {
    this.setData({ helpVisible: true });
  },
  hideHelpCenter() {
    this.setData({ helpVisible: false });
  },
  noop() {
  },
  showLoginGuide() {
    wx.showModal({
      title: '登录说明',
      content: '萌萌记账使用微信一键登录。若登录失败，请确认网络正常、微信开发者工具 AppID 配置正确，并重新点击登录按钮。',
      showCancel: false,
      confirmText: '我知道了',
    });
  },
  onContactResult(event: any) {
    if (event?.detail?.errMsg && event.detail.errMsg.indexOf('fail') !== -1) {
      wx.showToast({ title: '暂时无法打开客服，请稍后再试', icon: 'none' });
    }
  },
});
