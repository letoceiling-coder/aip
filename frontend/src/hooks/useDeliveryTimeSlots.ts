import { useMemo } from "react";
import {
  getAvailableTimeSlots,
  isTimeSlotDisabled,
  filterOutAsapOption,
} from "@/utils/deliveryTimeUtils";

interface UseDeliveryTimeSlotsOptions {
  timeSlots: string[];
  selectedDate: string | null;
  leadTimeHours?: number;
  filterAsap?: boolean;
}

/**
 * Хук для управления доступными временными слотами доставки
 * Автоматически фильтрует слоты по правилу lead time и убирает опцию "Как можно скорее"
 */
export function useDeliveryTimeSlots({
  timeSlots,
  selectedDate,
  leadTimeHours = 3,
  filterAsap = true,
}: UseDeliveryTimeSlotsOptions) {
  const availableSlots = useMemo(() => {
    // Сначала убираем опцию "Как можно скорее", если нужно
    let filteredSlots = filterAsap ? filterOutAsapOption(timeSlots) : timeSlots;

    // Затем фильтруем по доступности (lead time)
    if (selectedDate) {
      filteredSlots = getAvailableTimeSlots(filteredSlots, selectedDate, leadTimeHours);
    }

    return filteredSlots;
  }, [timeSlots, selectedDate, leadTimeHours, filterAsap]);

  const isSlotDisabled = (slotTime: string) => {
    return isTimeSlotDisabled(slotTime, selectedDate, leadTimeHours);
  };

  return {
    availableSlots,
    isSlotDisabled,
  };
}

