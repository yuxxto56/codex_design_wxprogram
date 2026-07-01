import { api } from '../../utils/api';
import { categoryIconPath } from '../../utils/category-icon';
import { centToYuan, currentDateText, currentMonth } from '../../utils/format';

function formatMonthLabel(month: string): string {
  const [year, monthNumber] = month.split('-');
  return `${year}年${monthNumber}月`;
}

Page({
  data: {
    dateText: currentDateText(),
    expenseText: '¥0.00',
    budgetText: '¥0.00',
    remainingText: '¥0.00',
    usedPercent: 0,
    progressWidth: 0,
    recentRecords: [] as any[],
    recentMonthLabel: '',
  },
  onShow() {
    this.loadSummary();
  },
  async loadSummary() {
    try {
      const month = currentMonth();
      const currentSummary = await api.homeSummary(month);
      const records = this.decorateRecords(currentSummary.recent_records || [], currentSummary.categories || []);
      this.setData({
        expenseText: centToYuan(currentSummary.expense_amount || 0),
        budgetText: centToYuan(currentSummary.budget_amount || 0),
        remainingText: centToYuan(currentSummary.remaining_amount || 0),
        usedPercent: currentSummary.used_percent || 0,
        progressWidth: Math.min(currentSummary.used_percent || 0, 100),
        recentRecords: records,
        recentMonthLabel: formatMonthLabel(month),
      });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  decorateRecords(records: any[], categories: any[]): any[] {
    return records.map((item: any) => ({
      ...item,
      id: Number(item.id),
      type: Number(item.type),
      amount: Number(item.amount || 0),
      category_id: Number(item.category_id),
      amount_text: centToYuan(Number(item.amount || 0)),
      category_name: this.categoryName(categories || [], Number(item.category_id)),
      category_icon_path: this.categoryIcon(categories || [], Number(item.category_id)),
      category_color: this.categoryColor(categories || [], Number(item.category_id)),
    }));
  },
  categoryName(categories: any[], id: number): string {
    const found = categories.find((item) => Number(item.id) === Number(id));
    return found ? found.name : '';
  },
  categoryIcon(categories: any[], id: number): string {
    const found = categories.find((item) => Number(item.id) === Number(id));
    return categoryIconPath(found?.icon);
  },
  categoryColor(categories: any[], id: number): string {
    const found = categories.find((item) => Number(item.id) === Number(id));
    return found?.color || '#F2E1BB';
  },
  goBill() {
    wx.switchTab({ url: '/pages/bill/bill' });
  },
});
