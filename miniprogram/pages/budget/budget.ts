import { api } from '../../utils/api';
import { currentMonth } from '../../utils/format';

Page({
  data: {
    month: currentMonth(),
    amount: '',
    saving: false,
  },
  onLoad() {
    this.loadBudget();
  },
  async loadBudget() {
    try {
      const budget = await api.monthBudget(this.data.month);
      this.setData({ amount: budget.amount ? (budget.amount / 100).toFixed(2) : '' });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  onAmountInput(event: any) {
    this.setData({ amount: event.detail.value });
  },
  goBack() {
    wx.navigateBack();
  },
  async saveBudget() {
    if (!this.data.amount) {
      wx.showToast({ title: '请填写预算金额', icon: 'none' });
      return;
    }
    this.setData({ saving: true });
    try {
      await api.saveBudget(this.data.month, this.data.amount);
      wx.showToast({ title: '保存成功', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 500);
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ saving: false });
    }
  },
});
