export function centToYuan(amount: number): string {
  return `¥${(amount / 100).toFixed(2)}`;
}

export function currentMonth(): string {
  const now = new Date();
  const month = `${now.getMonth() + 1}`.padStart(2, '0');
  return `${now.getFullYear()}-${month}`;
}

export function currentDateText(): string {
  const now = new Date();
  const week = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'][now.getDay()];
  return `${now.getMonth() + 1}月${now.getDate()}日 ${week}`;
}
