import { api } from '../../utils/api';
import { clearToken } from '../../utils/storage';
import { centToYuan, currentMonth } from '../../utils/format';

const MAX_AVATAR_SIZE = 5 * 1024 * 1024;
const app = () => getApp<{ globalData: { apiBaseUrl: string } }>();

Page({
  data: {
    user: {} as any,
    avatarPreview: '',
    incomeText: '¥0.00',
    remainingText: '¥0.00',
    profileModalVisible: false,
    infoModalVisible: false,
    infoTitle: '',
    infoContent: '',
    nicknameInput: '',
    savingProfile: false,
    uploadingAvatar: false,
  },
  onShow() {
    this.loadProfile();
  },
  async loadProfile() {
    try {
      const [user, summary] = await Promise.all([
        api.profile(),
        api.homeSummary(currentMonth()),
      ]);
      this.setData({
        user,
        avatarPreview: this.resolveAvatarUrl(user.avatar || ''),
        incomeText: centToYuan(summary.income_amount || 0),
        remainingText: centToYuan(summary.remaining_amount || 0),
      });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  goBudget() {
    wx.navigateTo({ url: '/pages/budget/budget' });
  },
  openProfileSettings() {
    this.setData({
      profileModalVisible: true,
      nicknameInput: this.data.user.nickname || '糯米豆',
      avatarPreview: this.resolveAvatarUrl(this.data.user.avatar || ''),
    });
  },
  closeProfileSettings() {
    this.setData({ profileModalVisible: false });
  },
  onNicknameInput(event: any) {
    this.setData({ nicknameInput: event.detail.value });
  },
  resolveAvatarUrl(avatar: string) {
    if (!avatar) {
      return '';
    }
    if (avatar.indexOf('/uploads/') === 0) {
      return `${app().globalData.apiBaseUrl}${avatar}`;
    }
    return avatar;
  },
  chooseAvatar() {
    if (this.data.uploadingAvatar) {
      return;
    }
    wx.chooseMedia({
      count: 1,
      mediaType: ['image'],
      sourceType: ['album', 'camera'],
      success: async (result: any) => {
        const file = result.tempFiles && result.tempFiles[0];
        if (!file || !file.tempFilePath) {
          return;
        }
        if ((file.size || 0) > MAX_AVATAR_SIZE) {
          wx.showToast({ title: '头像最大支持5M', icon: 'none' });
          return;
        }
        this.setData({ uploadingAvatar: true });
        try {
          const user = await api.uploadAvatar(file.tempFilePath);
          this.setData({
            user,
            avatarPreview: this.resolveAvatarUrl(user.avatar || ''),
          });
          wx.showToast({ title: '头像已更新', icon: 'success' });
        } catch (error: any) {
          wx.showToast({ title: error.message, icon: 'none' });
        } finally {
          this.setData({ uploadingAvatar: false });
        }
      },
      fail: () => {},
    });
  },
  async saveProfile() {
    if (!this.data.nicknameInput.trim()) {
      wx.showToast({ title: '请填写昵称', icon: 'none' });
      return;
    }
    this.setData({ savingProfile: true });
    try {
      const user = await api.saveProfile({
        nickname: this.data.nicknameInput,
        avatar: this.data.user.avatar || '',
      });
      this.setData({
        user,
        avatarPreview: this.resolveAvatarUrl(user.avatar || ''),
        profileModalVisible: false,
      });
      wx.showToast({ title: '已保存', icon: 'success' });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ savingProfile: false });
    }
  },
  openSkin() {
    this.setData({
      infoModalVisible: true,
      infoTitle: '个性皮肤',
      infoContent: '当前使用 Pastel Ledger 柔和主题。更多主题会在后续版本开放。',
    });
  },
  openAbout() {
    this.setData({
      infoModalVisible: true,
      infoTitle: '关于我们',
      infoContent: '萌萌记账是一款轻量个人记账小程序，专注快速记录、月度预算和温柔的账单回顾。',
    });
  },
  closeInfoModal() {
    this.setData({ infoModalVisible: false });
  },
  noop() {},
  logout() {
    clearToken();
    wx.reLaunch({ url: '/pages/login/login' });
  },
});
