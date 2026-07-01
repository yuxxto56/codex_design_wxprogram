import { api } from '../../utils/api';
import { categoryIconPath } from '../../utils/category-icon';

function pad(value: number): string {
  return `${value}`.padStart(2, '0');
}

function dateValue(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function timeValue(date: Date): string {
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toTimestamp(dateText: string, timeText: string): number {
  const [year, month, day] = dateText.split('-').map(Number);
  const [hour, minute] = timeText.split(':').map(Number);
  return Math.floor(new Date(year, month - 1, day, hour, minute, 0).getTime() / 1000);
}

Page({
  data: {
    type: 1,
    amount: '',
    remark: '',
    categoryId: 0,
    categories: [] as any[],
    allCategories: { expense: [], income: [] } as any,
    happenedDate: dateValue(new Date()),
    happenedTime: timeValue(new Date()),
    categoryModalVisible: false,
    newCategoryName: '',
    keyboardKeys: [
      { label: '1', value: '1', className: '' },
      { label: '2', value: '2', className: '' },
      { label: '3', value: '3', className: '' },
      { label: '4', value: '4', className: '' },
      { label: '5', value: '5', className: '' },
      { label: '6', value: '6', className: '' },
      { label: '7', value: '7', className: '' },
      { label: '8', value: '8', className: '' },
      { label: '9', value: '9', className: '' },
      { label: '.', value: '.', className: '' },
      { label: '0', value: '0', className: '' },
      { label: '⌫', value: 'backspace', className: 'delete-key' },
    ],
    saving: false,
  },
  refreshCurrentCategories(allCategories?: any, preferredCategoryId?: number) {
    const source = allCategories || this.data.allCategories;
    const preferred = preferredCategoryId || this.data.categoryId;
    const categories = this.withInitials(this.data.type === 1 ? source.expense : source.income);
    const exists = categories.some((item) => Number(item.id) === Number(preferred));
    this.setData({
      allCategories: source,
      categories,
      categoryId: exists ? preferred : categories[0]?.id || 0,
    });
  },
  onShow() {
    this.loadCategories();
  },
  async loadCategories() {
    const allCategories = await api.categories();
    this.refreshCurrentCategories(allCategories);
  },
  switchType(event: any) {
    const type = Number(event.currentTarget.dataset.type);
    if (type === this.data.type) {
      return;
    }
    const categories = this.withInitials(type === 1 ? this.data.allCategories.expense : this.data.allCategories.income);
    this.setData({
      type,
      amount: '',
      categories,
      categoryId: categories[0]?.id || 0,
    });
  },
  withInitials(categories: any[]) {
    return categories.map((item) => ({
      ...item,
      id: Number(item.id),
      type: Number(item.type),
      icon_path: categoryIconPath(item.icon),
    }));
  },
  selectCategory(event: any) {
    this.setData({ categoryId: Number(event.currentTarget.dataset.id) });
  },
  onRemarkInput(event: any) {
    this.setData({ remark: event.detail.value });
  },
  onDateChange(event: any) {
    this.setData({ happenedDate: event.detail.value });
  },
  onTimeChange(event: any) {
    this.setData({ happenedTime: event.detail.value });
  },
  openCategoryModal() {
    this.setData({ categoryModalVisible: true, newCategoryName: '' });
  },
  closeCategoryModal() {
    this.setData({ categoryModalVisible: false });
  },
  noop() {},
  onNewCategoryInput(event: any) {
    this.setData({ newCategoryName: event.detail.value });
  },
  async saveCategory() {
    const name = this.data.newCategoryName.trim();
    if (!name) {
      wx.showToast({ title: '请填写分类名称', icon: 'none' });
      return;
    }
    try {
      const category = await api.createCategory({ type: this.data.type, name });
      const allCategories = await api.categories();
      this.setData({ categoryModalVisible: false, newCategoryName: '' });
      this.refreshCurrentCategories(allCategories, category.id);
      wx.showToast({ title: '分类已添加', icon: 'success' });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  onKeyTap(event: any) {
    const key = String(event.currentTarget.dataset.key || '');
    if (key === 'backspace') {
      this.backspace();
      return;
    }
    const next = `${this.data.amount}${key}`;
    if (/^\d{0,7}(\.\d{0,2})?$/.test(next)) {
      this.setData({ amount: next });
    }
  },
  backspace() {
    this.setData({ amount: this.data.amount.slice(0, -1) });
  },
  async saveRecord() {
    if (!this.data.amount || !this.data.categoryId) {
      wx.showToast({ title: '请填写金额和分类', icon: 'none' });
      return;
    }
    this.setData({ saving: true });
    try {
      await api.createRecord({
        type: this.data.type,
        category_id: this.data.categoryId,
        amount: this.data.amount,
        remark: this.data.remark,
        happened_at: toTimestamp(this.data.happenedDate, this.data.happenedTime),
      });
      wx.showToast({ title: '保存成功', icon: 'success' });
      this.setData({ amount: '', remark: '' });
      wx.switchTab({ url: '/pages/home/home' });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ saving: false });
    }
  },
});
