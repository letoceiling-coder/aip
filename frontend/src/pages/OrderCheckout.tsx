import { useState } from "react";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { DeliveryTimeSelector } from "@/components/DeliveryTimeSelector";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Calendar, User, Phone, MapPin, Loader2 } from "lucide-react";
import { toast } from "sonner";

/**
 * Пример страницы оформления заказа с выбором времени доставки
 * 
 * Использует DeliveryTimeSelector, который автоматически:
 * - Убирает опцию "Как можно скорее"
 * - Фильтрует слоты по правилу lead time (3 часа)
 * - Для будущих дат все слоты доступны
 */
const OrderCheckout = () => {
  const [formData, setFormData] = useState({
    name: "",
    phone: "",
    address: "",
    date: "",
    time: "",
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(false);

  // Все доступные временные слоты (включая "Как можно скорее", которое будет автоматически отфильтровано)
  const allTimeSlots = [
    "Как можно скорее", // Эта опция будет автоматически убрана
    "10:00",
    "11:00",
    "12:00",
    "13:00",
    "14:00",
    "15:00",
    "16:00",
    "17:00",
    "18:00",
    "19:00",
    "20:00",
  ];

  // Получаем минимальную дату (сегодня или завтра в зависимости от времени)
  const getMinDate = () => {
    const today = new Date();
    return today.toISOString().split("T")[0];
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    
    // При смене даты сбрасываем время, если оно стало недоступно
    if (name === "date" && formData.time) {
      // Можно добавить проверку доступности времени
    }
    
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: "" }));
    }
  };

  const handleTimeChange = (time: string) => {
    setFormData((prev) => ({ ...prev, time }));
    if (errors.time) {
      setErrors((prev) => ({ ...prev, time: "" }));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setErrors({});

    try {
      // Валидация
      if (!formData.name) {
        setErrors((prev) => ({ ...prev, name: "Введите имя" }));
      }
      if (!formData.phone) {
        setErrors((prev) => ({ ...prev, phone: "Введите телефон" }));
      }
      if (!formData.address) {
        setErrors((prev) => ({ ...prev, address: "Введите адрес доставки" }));
      }
      if (!formData.date) {
        setErrors((prev) => ({ ...prev, date: "Выберите дату доставки" }));
      }
      if (!formData.time) {
        setErrors((prev) => ({ ...prev, time: "Выберите время доставки" }));
      }

      if (Object.keys(errors).length > 0) {
        return;
      }

      // Симуляция отправки заказа
      await new Promise((resolve) => setTimeout(resolve, 1000));

      toast.success("Заказ успешно оформлен!");
      
      // Сброс формы
      setFormData({
        name: "",
        phone: "",
        address: "",
        date: "",
        time: "",
      });
    } catch (error) {
      toast.error("Произошла ошибка при оформлении заказа");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background flex flex-col">
      <Header />
      
      <main className="flex-1 container mx-auto px-4 py-8 max-w-2xl">
        <h1 className="text-3xl font-bold mb-8">Оформление заказа</h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Контактная информация */}
          <div className="space-y-4">
            <h2 className="text-xl font-semibold">Контактная информация</h2>
            
            <div className="space-y-2">
              <Label htmlFor="name" className="flex items-center gap-2">
                <User className="w-4 h-4 text-muted-foreground" />
                Имя
              </Label>
              <Input
                id="name"
                name="name"
                value={formData.name}
                onChange={handleChange}
                placeholder="Иван Иванов"
                className={errors.name ? "border-destructive" : ""}
              />
              {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="phone" className="flex items-center gap-2">
                <Phone className="w-4 h-4 text-muted-foreground" />
                Телефон
              </Label>
              <Input
                id="phone"
                name="phone"
                type="tel"
                value={formData.phone}
                onChange={handleChange}
                placeholder="+7 (900) 000-00-00"
                className={errors.phone ? "border-destructive" : ""}
              />
              {errors.phone && <p className="text-xs text-destructive">{errors.phone}</p>}
            </div>
          </div>

          {/* Адрес доставки */}
          <div className="space-y-4">
            <h2 className="text-xl font-semibold">Адрес доставки</h2>
            
            <div className="space-y-2">
              <Label htmlFor="address" className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-muted-foreground" />
                Адрес
              </Label>
              <Input
                id="address"
                name="address"
                value={formData.address}
                onChange={handleChange}
                placeholder="Город, улица, дом, квартира"
                className={errors.address ? "border-destructive" : ""}
              />
              {errors.address && <p className="text-xs text-destructive">{errors.address}</p>}
            </div>
          </div>

          {/* Время доставки */}
          <div className="space-y-4">
            <h2 className="text-xl font-semibold">Время доставки</h2>
            
            <div className="space-y-2">
              <Label htmlFor="date" className="flex items-center gap-2">
                <Calendar className="w-4 h-4 text-muted-foreground" />
                Дата доставки
              </Label>
              <Input
                id="date"
                name="date"
                type="date"
                value={formData.date}
                onChange={handleChange}
                min={getMinDate()}
                className={errors.date ? "border-destructive" : ""}
              />
              {errors.date && <p className="text-xs text-destructive">{errors.date}</p>}
            </div>

            {/* Компонент выбора времени с автоматической фильтрацией */}
            <DeliveryTimeSelector
              timeSlots={allTimeSlots}
              selectedDate={formData.date}
              selectedTime={formData.time}
              onTimeChange={handleTimeChange}
              leadTimeHours={3}
              error={errors.time}
            />
          </div>

          <Button
            type="submit"
            disabled={isLoading}
            className="w-full min-h-[44px]"
          >
            {isLoading ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin mr-2" />
                Оформление...
              </>
            ) : (
              "Оформить заказ"
            )}
          </Button>
        </form>
      </main>

      <Footer />
    </div>
  );
};

export default OrderCheckout;

