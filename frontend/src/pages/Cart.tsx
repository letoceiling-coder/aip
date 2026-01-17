import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { Button } from "@/components/ui/button";
import { ArrowLeft, ShoppingCart, Trash2, Plus, Minus } from "lucide-react";
import { toast } from "sonner";

/**
 * Страница корзины с товарами
 * Товары кликабельны и ведут на страницу карточки товара
 */
const Cart = () => {
  const navigate = useNavigate();

  // Mock данные корзины (в реальном приложении это будет из state/API)
  const [cartItems, setCartItems] = useState([
    {
      id: "1",
      title: "3-комнатная квартира в ЖК «Белый город»",
      price: 6500000,
      image: "https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=800&h=600&fit=crop",
      area: 85,
      rooms: 3,
      quantity: 1,
    },
    {
      id: "2",
      title: "2-комнатная квартира с видом на парк",
      price: 4800000,
      image: "https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800&h=600&fit=crop",
      area: 62,
      rooms: 2,
      quantity: 1,
    },
  ]);

  const handleItemClick = (itemId: string) => {
    // Переход на страницу товара с пометкой, что пришли из корзины
    navigate(`/property/${itemId}?from=cart`);
  };

  const handleRemoveItem = (itemId: string) => {
    setCartItems((prev) => prev.filter((item) => item.id !== itemId));
    toast.success("Товар удалён из корзины");
  };

  const handleQuantityChange = (itemId: string, delta: number) => {
    setCartItems((prev) =>
      prev.map((item) => {
        if (item.id === itemId) {
          const newQuantity = Math.max(1, item.quantity + delta);
          return { ...item, quantity: newQuantity };
        }
        return item;
      })
    );
  };

  const total = cartItems.reduce((sum, item) => sum + item.price * item.quantity, 0);

  if (cartItems.length === 0) {
    return (
      <div className="min-h-screen bg-background flex flex-col">
        <Header />
        <main className="flex-1 container mx-auto px-4 py-8">
          <div className="max-w-2xl mx-auto text-center py-16">
            <div className="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-6">
              <ShoppingCart className="w-10 h-10 text-primary" />
            </div>
            <h1 className="text-2xl font-display font-bold text-foreground mb-3">
              Корзина пуста
            </h1>
            <p className="text-muted-foreground mb-6">
              Добавьте товары в корзину для оформления заказа
            </p>
            <Link to="/catalog">
              <Button variant="primary">Перейти в каталог</Button>
            </Link>
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background flex flex-col">
      <Header />
      
      <main className="flex-1 container mx-auto px-4 py-8">
        <div className="max-w-4xl mx-auto">
          {/* Header */}
          <div className="flex items-center gap-4 mb-6">
            <Link
              to="/catalog"
              className="flex items-center gap-2 text-muted-foreground hover:text-primary transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
              <span className="hidden sm:inline">Назад в каталог</span>
            </Link>
          </div>

          <h1 className="text-3xl font-bold mb-8">Корзина</h1>

          {/* Cart Items */}
          <div className="space-y-4 mb-8">
            {cartItems.map((item) => (
              <div
                key={item.id}
                className="bg-card rounded-xl border border-border p-4 hover:shadow-md transition-shadow"
              >
                <div className="flex gap-4">
                  {/* Кликабельное изображение товара */}
                  <div
                    onClick={() => handleItemClick(item.id)}
                    className="cursor-pointer flex-shrink-0"
                  >
                    <img
                      src={item.image}
                      alt={item.title}
                      className="w-24 h-24 rounded-lg object-cover hover:opacity-90 transition-opacity"
                    />
                  </div>

                  {/* Информация о товаре */}
                  <div className="flex-1 min-w-0">
                    {/* Кликабельный заголовок */}
                    <h3
                      onClick={() => handleItemClick(item.id)}
                      className="font-semibold text-foreground hover:text-primary transition-colors cursor-pointer mb-2"
                    >
                      {item.title}
                    </h3>
                    <div className="text-sm text-muted-foreground mb-3">
                      <span>{item.rooms} комн.</span> · <span>{item.area} м²</span>
                    </div>

                    {/* Цена и количество */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2 border border-border rounded-lg">
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => handleQuantityChange(item.id, -1)}
                            disabled={item.quantity <= 1}
                          >
                            <Minus className="w-4 h-4" />
                          </Button>
                          <span className="w-8 text-center text-sm font-medium">
                            {item.quantity}
                          </span>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => handleQuantityChange(item.id, 1)}
                          >
                            <Plus className="w-4 h-4" />
                          </Button>
                        </div>
                        <div className="text-lg font-semibold text-foreground">
                          {item.price.toLocaleString("ru-RU")} ₽
                        </div>
                      </div>

                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleRemoveItem(item.id)}
                        className="text-muted-foreground hover:text-destructive"
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Order Summary */}
          <div className="bg-card rounded-xl border border-border p-6 sticky bottom-4">
            <div className="flex items-center justify-between mb-4">
              <span className="text-lg font-semibold text-foreground">Итого:</span>
              <span className="text-2xl font-bold text-foreground">
                {total.toLocaleString("ru-RU")} ₽
              </span>
            </div>
            <Button
              variant="primary"
              size="lg"
              className="w-full"
              onClick={() => navigate("/order-checkout")}
            >
              Оформить заказ
            </Button>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default Cart;

