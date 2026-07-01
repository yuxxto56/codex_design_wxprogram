import { api } from '../../utils/api';
import { categoryIconPath } from '../../utils/category-icon';
import { centToYuan, currentMonth } from '../../utils/format';

function pad(value: number): string {
  return `${value}`.padStart(2, '0');
}

function dateValue(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function timeValue(date: Date): string {
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function timestampFrom(dateText: string, timeText: string): number {
  const [year, month, day] = dateText.split('-').map(Number);
  const [hour, minute] = timeText.split(':').map(Number);
  return Math.floor(new Date(year, month - 1, day, hour, minute, 0).getTime() / 1000);
}

function amountYuan(amount: number): string {
  return (Number(amount || 0) / 100).toFixed(2);
}

function monthLabel(month: string): string {
  const [year, monthNumber] = month.split('-');
  return year && monthNumber ? `${year}年${monthNumber}月` : '';
}

function recordsFromGroups(groups: Record<string, any[]> = {}): any[] {
  const records: any[] = [];
  Object.keys(groups).forEach((day) => {
    const dayRecords = Array.isArray(groups[day]) ? groups[day] : [];
    dayRecords.forEach((record) => records.push(record));
  });
  return records;
}

function normalizeCategories(categories: any = {}): any[] {
  return [...(categories.expense || []), ...(categories.income || [])].map((item: any) => ({
    ...item,
    id: Number(item.id),
    type: Number(item.type),
  }));
}

Page({
  data: {
    month: currentMonth(),
    monthNote: '',
    totalExpenseText: '¥0.00',
    compareText: '比上月节省了 ¥0.00',
    groups: [] as any[],
    allCategories: [] as any[],
    detailVisible: false,
    editMode: false,
    detail: {} as any,
    editRecord: {} as any,
    editCategories: [] as any[],
  },
  onShow() {
    this.loadRecords();
  },
  async loadRecords() {
    try {
      const list = await api.recordList(this.data.month);
      await this.renderRecordList(list, this.data.month);
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  async renderRecordList(list: any, month: string) {
    try {
      let allCategories = this.data.allCategories;
      try {
        allCategories = normalizeCategories(await api.categories());
      } catch (error) {
        allCategories = [];
      }
      const listGroups = list.groups || {};
      const groups = Object.keys(listGroups).map((day) => ({
        day: this.friendlyDay(day),
        records: (Array.isArray(listGroups[day]) ? listGroups[day] : []).map((record: any) => this.decorateRecord(record, allCategories)),
      }));
      const allRecords = recordsFromGroups(listGroups).map((record: any) => this.decorateRecord(record, allCategories));
      const totalExpense = this.totalExpense(allRecords);
      this.setData({
        groups,
        allCategories,
        monthNote: `当前月份：${monthLabel(month)}`,
        totalExpenseText: centToYuan(totalExpense),
        compareText: '正在计算上月对比',
      });
      this.loadPreviousCompare(totalExpense, month);
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  async loadPreviousCompare(totalExpense: number, month: string) {
    try {
      const previousList = await api.recordList(this.previousMonth(month));
      const previousRecords = recordsFromGroups(previousList.groups || {});
      const previousExpense = this.totalExpense(previousRecords);
      this.setData({ compareText: this.compareExpense(totalExpense, previousExpense) });
    } catch (error) {
      this.setData({ compareText: '上月对比暂不可用' });
    }
  },
  previousMonth(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 2, 1);
    return `${date.getFullYear()}-${`${date.getMonth() + 1}`.padStart(2, '0')}`;
  },
  totalExpense(records: any[]): number {
    return records
      .filter((record: any) => Number(record.type) === 1)
      .reduce((sum: number, record: any) => sum + Number(record.amount || 0), 0);
  },
  compareExpense(current: number, previous: number): string {
    if (current === 0) {
      return '这个月还没有支出';
    }
    const diff = previous - current;
    if (diff > 0) {
      return `比上月节省了 ${centToYuan(diff)}`;
    }
    if (diff < 0) {
      return `比上月多花了 ${centToYuan(Math.abs(diff))}`;
    }
    return '和上月支出持平';
  },
  friendlyDay(day: string): string {
    const now = new Date();
    const today = `${now.getFullYear()}-${`${now.getMonth() + 1}`.padStart(2, '0')}-${`${now.getDate()}`.padStart(2, '0')}`;
    const yesterdayDate = new Date(now.getTime() - 24 * 60 * 60 * 1000);
    const yesterday = `${yesterdayDate.getFullYear()}-${`${yesterdayDate.getMonth() + 1}`.padStart(2, '0')}-${`${yesterdayDate.getDate()}`.padStart(2, '0')}`;
    const label = day === today ? '今天' : day === yesterday ? '昨天' : '';
    const parts = day.split('-');
    const dateText = parts.length === 3 ? `${Number(parts[1])}月${Number(parts[2])}日` : day;
    return label ? `${label} · ${dateText}` : dateText;
  },
  decorateRecord(record: any, categories: any[]) {
    const normalized = {
      ...record,
      id: Number(record.id),
      type: Number(record.type),
      category_id: Number(record.category_id),
      amount: Number(record.amount || 0),
      happened_at: Number(record.happened_at || 0),
    };
    const category = categories.find((item) => Number(item.id) === normalized.category_id) || {};
    const date = new Date(normalized.happened_at * 1000);
    return {
      ...normalized,
      category_name: category.name || '分类',
      category_icon_path: categoryIconPath(category.icon),
      category_color: category.color || '#F2E1BB',
      amount_text: centToYuan(normalized.amount),
      time_text: `${`${date.getHours()}`.padStart(2, '0')}:${`${date.getMinutes()}`.padStart(2, '0')}`,
    };
  },
  onMonthChange(event: any) {
    this.setData({ month: event.detail.value });
    this.loadRecords();
  },
  async openDetail(event: any) {
    const id = Number(event.currentTarget.dataset.id);
    try {
      const raw = await api.recordDetail(id);
      const record = this.decorateRecord(raw, this.data.allCategories);
      this.setData({ detail: record, detailVisible: true, editMode: false });
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  closeDetail() {
    this.setData({ detailVisible: false, editMode: false });
  },
  noop() {},
  startEdit() {
    const happenedAt = new Date(Number(this.data.detail.happened_at || 0) * 1000);
    const editRecord = {
      id: this.data.detail.id,
      type: Number(this.data.detail.type),
      category_id: Number(this.data.detail.category_id),
      amount: amountYuan(Number(this.data.detail.amount || 0)),
      remark: this.data.detail.remark || '',
      happenedDate: dateValue(happenedAt),
      happenedTime: timeValue(happenedAt),
    };
    this.setData({
      editMode: true,
      editRecord,
      editCategories: this.categoriesByType(editRecord.type),
    });
  },
  categoriesByType(type: number): any[] {
    return this.data.allCategories.filter((item: any) => Number(item.type) === Number(type));
  },
  switchEditType(event: any) {
    const type = Number(event.currentTarget.dataset.type);
    const editCategories = this.categoriesByType(type);
    this.setData({
      editRecord: {
        ...this.data.editRecord,
        type,
        category_id: editCategories[0]?.id || 0,
      },
      editCategories,
    });
  },
  selectEditCategory(event: any) {
    this.setData({
      editRecord: {
        ...this.data.editRecord,
        category_id: Number(event.currentTarget.dataset.id),
      },
    });
  },
  onEditAmountInput(event: any) {
    this.setData({ editRecord: { ...this.data.editRecord, amount: event.detail.value } });
  },
  onEditRemarkInput(event: any) {
    this.setData({ editRecord: { ...this.data.editRecord, remark: event.detail.value } });
  },
  onEditDateChange(event: any) {
    this.setData({ editRecord: { ...this.data.editRecord, happenedDate: event.detail.value } });
  },
  onEditTimeChange(event: any) {
    this.setData({ editRecord: { ...this.data.editRecord, happenedTime: event.detail.value } });
  },
  async saveEdit() {
    if (!this.data.editRecord.amount || !this.data.editRecord.category_id) {
      wx.showToast({ title: '请填写金额和分类', icon: 'none' });
      return;
    }
    try {
      await api.updateRecord({
        id: this.data.editRecord.id,
        type: this.data.editRecord.type,
        category_id: this.data.editRecord.category_id,
        amount: this.data.editRecord.amount,
        remark: this.data.editRecord.remark,
        happened_at: timestampFrom(this.data.editRecord.happenedDate, this.data.editRecord.happenedTime),
      });
      wx.showToast({ title: '已保存', icon: 'success' });
      this.setData({ detailVisible: false, editMode: false });
      this.loadRecords();
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  async deleteRecord() {
    try {
      await api.deleteRecord(this.data.detail.id);
      this.setData({ detailVisible: false });
      wx.showToast({ title: '已删除', icon: 'success' });
      this.loadRecords();
    } catch (error: any) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
  goRecord() {
    wx.switchTab({ url: '/pages/record/record' });
  },
});
