import { Clock } from "lucide-react";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useDeliveryTimeSlots } from "@/hooks/useDeliveryTimeSlots";

interface DeliveryTimeSelectorProps {
  timeSlots: string[];
  selectedDate: string | null;
  selectedTime: string;
  onTimeChange: (time: string) => void;
  leadTimeHours?: number;
  error?: string;
  label?: string;
}

/**
 * Компонент для выбора времени доставки
 * Автоматически:
 * - Убирает опцию "Как можно скорее"
 * - Фильтрует слоты по правилу lead time (3 часа от текущего времени)
 * - Для будущих дат все слоты доступны
 */
export function DeliveryTimeSelector({
  timeSlots,
  selectedDate,
  selectedTime,
  onTimeChange,
  leadTimeHours = 3,
  error,
  label = "Время доставки",
}: DeliveryTimeSelectorProps) {
  const { availableSlots, isSlotDisabled } = useDeliveryTimeSlots({
    timeSlots,
    selectedDate,
    leadTimeHours,
    filterAsap: true, // Всегда убираем опцию "Как можно скорее"
  });

  return (
    <div className="space-y-2">
      <Label className="flex items-center gap-2">
        <Clock className="w-4 h-4 text-muted-foreground" />
        {label}
      </Label>
      <Select value={selectedTime} onValueChange={onTimeChange}>
        <SelectTrigger className={error ? "border-destructive" : ""}>
          <SelectValue placeholder="Выберите время" />
        </SelectTrigger>
        <SelectContent>
          {availableSlots.length > 0 ? (
            availableSlots.map((slot) => (
              <SelectItem key={slot} value={slot}>
                {slot}
              </SelectItem>
            ))
          ) : (
            <SelectItem value="" disabled>
              Нет доступных слотов
            </SelectItem>
          )}
        </SelectContent>
      </Select>
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}

