const categoryIconPaths: Record<string, string> = {
  restaurant: '/assets/icons/category/restaurant.svg',
  shopping_bag: '/assets/icons/category/shopping-bag.svg',
  directions_car: '/assets/icons/category/car.svg',
  shopping_cart: '/assets/icons/category/cart.svg',
  auto_awesome: '/assets/icons/category/sparkle.svg',
  payments: '/assets/icons/category/wallet.svg',
  work: '/assets/icons/category/work.svg',
  savings: '/assets/icons/category/savings.svg',
};

export function categoryIconPath(icon: string | undefined): string {
  if (!icon) {
    return '/assets/icons/category/sparkle.svg';
  }

  return categoryIconPaths[icon] || '/assets/icons/category/sparkle.svg';
}
