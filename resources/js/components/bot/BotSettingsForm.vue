<template>
    <div class="bot-settings-form space-y-6">
        <h2 class="text-2xl font-semibold">Настройки бота</h2>

        <!-- Tabs -->
        <div class="border-b border-border">
            <nav class="flex -mb-px">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    :class="[
                        'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                        activeTab === tab.key
                            ? 'border-accent text-accent'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <form @submit.prevent="saveSettings" class="space-y-6">
            <!-- Основные настройки -->
            <div v-if="activeTab === 'main'" class="bg-card rounded-lg border border-border p-6 space-y-4">
                <h3 class="text-lg font-semibold">Основные настройки</h3>

                <div>
                    <label class="block text-sm font-medium mb-2">ID канала</label>
                    <input
                        v-model.number="form.required_channel_id"
                        type="number"
                        placeholder="-1001234567890"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        ID канала (можно получить через бота @userinfobot)
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Username канала</label>
                    <input
                        v-model="form.required_channel_username"
                        type="text"
                        placeholder="aip_channel"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        Username канала без символа @
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Telegram ID администраторов</label>
                    <div class="space-y-2">
                        <div
                            v-for="(adminId, index) in form.admin_telegram_ids"
                            :key="index"
                            class="flex gap-2"
                        >
                            <input
                                v-model.number="form.admin_telegram_ids[index]"
                                type="number"
                                placeholder="123456789"
                                class="flex-1 h-10 px-3 border border-border rounded-lg bg-background"
                            />
                            <button
                                type="button"
                                @click="removeAdmin(index)"
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg"
                            >
                                Удалить
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="addAdmin"
                            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg"
                        >
                            + Добавить администратора
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Ссылка на Яндекс Карты</label>
                    <input
                        v-model="form.yandex_maps_url"
                        type="url"
                        placeholder="https://yandex.ru/maps/org/..."
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Медиа перед приветственным сообщением</label>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Тип медиа</label>
                            <select
                                v-model="form.welcome_media_type"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            >
                                <option value="">Нет медиа</option>
                                <option value="photo">Фото</option>
                                <option value="video">Видео</option>
                                <option value="gallery">Галерея фото (до 10)</option>
                            </select>
                        </div>
                        
                        <!-- Одно фото/видео -->
                        <div v-if="form.welcome_media_type === 'photo' || form.welcome_media_type === 'video'">
                            <div v-if="selectedWelcomeMedia" class="mb-3 p-3 bg-muted/30 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium">{{ selectedWelcomeMedia.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatFileSize(selectedWelcomeMedia.size) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeWelcomeMedia"
                                        class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="showWelcomeMediaPicker = true"
                                class="w-full h-10 px-4 border border-border rounded-lg bg-background hover:bg-muted/10"
                            >
                                {{ selectedWelcomeMedia ? 'Изменить файл' : 'Выбрать файл из медиа-библиотеки' }}
                            </button>
                        </div>
                        
                        <!-- Галерея фото -->
                        <div v-if="form.welcome_media_type === 'gallery'">
                            <div v-if="selectedWelcomeMediaGallery.length > 0" class="mb-3 space-y-2">
                                <div
                                    v-for="(media, index) in selectedWelcomeMediaGallery"
                                    :key="media.id"
                                    class="p-3 bg-muted/30 rounded-lg"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium">{{ media.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ formatFileSize(media.size) }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeWelcomeMediaFromGallery(index)"
                                            class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="showWelcomeMediaGalleryPicker = true"
                                class="w-full h-10 px-4 border border-border rounded-lg bg-background hover:bg-muted/10"
                            >
                                {{ selectedWelcomeMediaGallery.length > 0 ? 'Добавить еще фото' : 'Выбрать фото из медиа-библиотеки' }}
                            </button>
                            <p v-if="selectedWelcomeMediaGallery.length > 0" class="text-xs text-muted-foreground mt-1">
                                Выбрано: {{ selectedWelcomeMediaGallery.length }} фото (максимум 10)
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Приветственное сообщение</label>
                    <textarea
                        v-model="form.welcome_message"
                        rows="6"
                        placeholder="Добро пожаловать..."
                        class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Презентация для скачивания</label>
                    <div class="space-y-3">
                        <div v-if="selectedPresentationFile" class="mb-3 p-3 bg-muted/30 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">{{ selectedPresentationFile.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatFileSize(selectedPresentationFile.size) }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="removePresentationFile"
                                    class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showPresentationPicker = true"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background hover:bg-muted/10"
                        >
                            {{ selectedPresentationFile ? 'Изменить файл' : 'Выбрать файл из медиа-библиотеки' }}
                        </button>
                        <p class="text-xs text-muted-foreground">
                            Если файл не выбран, кнопка "Скачать презентацию" не будет отображаться в боте
                        </p>
                    </div>
                </div>

                <!-- Reply кнопки (кнопки под полем ввода) -->
                <div class="border-t border-border pt-4">
                    <h3 class="text-lg font-semibold mb-4">Reply кнопки (кнопки под полем ввода)</h3>
                    
                    <!-- Кнопка 1: Полезные материалы и договора, презентации -->
                    <div class="space-y-3 mb-6">
                        <label class="block text-sm font-medium">Кнопка 1: Полезные материалы и договора, презентации</label>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Текст кнопки</label>
                            <input
                                v-model="form.reply_buttons.materials_button_text"
                                type="text"
                                placeholder="📂 Полезные материалы и договора, презентации"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Файлы для отправки (множественный выбор)</label>
                            <div v-if="selectedMaterialsFiles.length > 0" class="mb-3 space-y-2">
                                <div
                                    v-for="(file, index) in selectedMaterialsFiles"
                                    :key="file.id"
                                    class="p-3 bg-muted/30 rounded-lg"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium">{{ file.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ formatFileSize(file.size) }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeMaterialsFile(index)"
                                            class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="showMaterialsPicker = true"
                                class="w-full h-10 px-4 border border-border rounded-lg bg-background hover:bg-muted/10"
                            >
                                {{ selectedMaterialsFiles.length > 0 ? 'Добавить еще файлы' : 'Выбрать файлы из медиа-библиотеки' }}
                            </button>
                            <p class="text-xs text-muted-foreground mt-1">
                                Выбрано: {{ selectedMaterialsFiles.length }} файлов
                            </p>
                        </div>
                    </div>

                    <!-- Кнопка 2: Записаться на консультацию -->
                    <div class="space-y-3 mb-6">
                        <label class="block text-sm font-medium">Кнопка 2: Записаться на консультацию</label>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Текст кнопки</label>
                            <input
                                v-model="form.reply_buttons.consultation_button_text"
                                type="text"
                                placeholder="📞 Записаться на консультацию"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            При нажатии будет использован существующий функционал записи на консультацию
                        </p>
                    </div>

                    <!-- Кнопка 3: Наш офис на Яндекс Картах -->
                    <div class="space-y-3 mb-6">
                        <label class="block text-sm font-medium">Кнопка 3: Наш офис на Яндекс Картах</label>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Текст кнопки</label>
                            <input
                                v-model="form.reply_buttons.office_button_text"
                                type="text"
                                placeholder="📍 Наш офис на Яндекс Картах"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Широта (latitude)</label>
                            <input
                                v-model.number="form.office_location.latitude"
                                type="number"
                                step="0.000001"
                                placeholder="55.7558"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Долгота (longitude)</label>
                            <input
                                v-model.number="form.office_location.longitude"
                                type="number"
                                step="0.000001"
                                placeholder="37.6173"
                                class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-muted-foreground mb-2">Полный адрес</label>
                            <textarea
                                v-model="form.office_location.address"
                                rows="3"
                                placeholder="г. Москва, ул. Примерная, д. 1"
                                class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                            ></textarea>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            При нажатии будет отправлена карта с указанными координатами и текстовое сообщение с адресом
                        </p>
                    </div>
                </div>
            </div>

            <!-- Тексты сообщений -->
            <div v-if="activeTab === 'messages'" class="bg-card rounded-lg border border-border p-6 space-y-6">
                <h3 class="text-lg font-semibold">Тексты сообщений бота</h3>

                <!-- Подписка на канал -->
                <div class="space-y-4">
                    <h4 class="text-md font-medium">Подписка на канал</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст экрана подписки</label>
                        <textarea
                            v-model="form.messages.subscription.required_text"
                            rows="3"
                            placeholder="Для доступа к бета-версии необходимо подписаться..."
                            class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки подписки</label>
                        <input
                            v-model="form.messages.subscription.subscribe_button"
                            type="text"
                            placeholder="🔔 Подписаться на Telegram"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки проверки</label>
                        <input
                            v-model="form.messages.subscription.check_button"
                            type="text"
                            placeholder="✅ Я подписался"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                </div>

                <!-- Консультация -->
                <div class="space-y-4 pt-4 border-t border-border">
                    <h4 class="text-md font-medium">Консультация</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2">Описание услуги</label>
                        <textarea
                            v-model="form.messages.consultation.description"
                            rows="4"
                            placeholder="Если вашему бизнесу нужна профессиональная..."
                            class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст поля "Имя"</label>
                        <input
                            v-model="form.messages.consultation.form_name_label"
                            type="text"
                            placeholder="Введите ваше имя:"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст поля "Телефон"</label>
                        <input
                            v-model="form.messages.consultation.form_phone_label"
                            type="text"
                            placeholder="Введите ваш телефон:"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст поля "Описание"</label>
                        <input
                            v-model="form.messages.consultation.form_description_label"
                            type="text"
                            placeholder="Краткое описание запроса (опционально...):"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Сообщение после отправки</label>
                        <textarea
                            v-model="form.messages.consultation.thank_you"
                            rows="2"
                            placeholder="Спасибо. Мы свяжемся с вами в ближайшее время."
                            class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Записаться"</label>
                        <input
                            v-model="form.messages.consultation.start_button"
                            type="text"
                            placeholder="📝 Записаться на консультацию"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Пропустить"</label>
                        <input
                            v-model="form.messages.consultation.skip_description_button"
                            type="text"
                            placeholder="Пропустить"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                </div>

                <!-- Материалы -->
                <div class="space-y-4 pt-4 border-t border-border">
                    <h4 class="text-md font-medium">Материалы</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2">Описание списка материалов</label>
                        <textarea
                            v-model="form.messages.materials.list_description"
                            rows="3"
                            placeholder="Мы подготовили материалы по ключевым направлениям..."
                            class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки скачивания</label>
                        <input
                            v-model="form.messages.materials.download_button"
                            type="text"
                            placeholder="⬇️ Скачать материалы"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Назад"</label>
                        <input
                            v-model="form.messages.materials.back_to_list"
                            type="text"
                            placeholder="⬅️ Назад"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                </div>

                <!-- Главное меню -->
                <div class="space-y-4 pt-4 border-t border-border">
                    <h4 class="text-md font-medium">Главное меню</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Материалы"</label>
                        <input
                            v-model="form.messages.menu.materials_button"
                            type="text"
                            placeholder="📂 Полезные материалы и договоры"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Консультация"</label>
                        <input
                            v-model="form.messages.menu.consultation_button"
                            type="text"
                            placeholder="📞 Записаться на консультацию"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Отзыв"</label>
                        <input
                            v-model="form.messages.menu.review_button"
                            type="text"
                            placeholder="⭐ Оставить отзыв на Яндекс Картах"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Назад в меню"</label>
                        <input
                            v-model="form.messages.menu.back_to_menu"
                            type="text"
                            placeholder="⬅️ Назад в меню"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Текст кнопки "Скачать презентацию"</label>
                        <input
                            v-model="form.messages.menu.presentation_button"
                            type="text"
                            placeholder="📥 Скачать презентацию"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                    </div>
                </div>

                <!-- Уведомления -->
                <div class="space-y-4 pt-4 border-t border-border">
                    <h4 class="text-md font-medium">Уведомления администраторам</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2">Шаблон уведомления о новой заявке</label>
                        <textarea
                            v-model="form.messages.notifications.consultation_template"
                            rows="6"
                            placeholder="Новая заявка на консультацию&#10;&#10;Имя: {name}&#10;Телефон: {phone}&#10;Описание: {description}&#10;Дата: {date}"
                            class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none font-mono text-sm"
                        ></textarea>
                        <p class="text-xs text-muted-foreground mt-1">
                            Используйте переменные: {name}, {phone}, {description}, {date}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Дополнительные настройки -->
            <div v-if="activeTab === 'advanced'" class="bg-card rounded-lg border border-border p-6 space-y-4">
                <h3 class="text-lg font-semibold">Дополнительные настройки</h3>

                <div>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.other_settings.phone_validation_strict"
                            type="checkbox"
                            class="w-4 h-4"
                        />
                        <span>Строгая валидация телефона</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Максимальная длина описания</label>
                    <input
                        v-model.number="form.other_settings.max_description_length"
                        type="number"
                        min="10"
                        max="5000"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Таймаут проверки подписки (сек)</label>
                    <input
                        v-model.number="form.other_settings.subscription_check_timeout"
                        type="number"
                        min="1"
                        max="30"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>

                <!-- Настройки отмененных заказов -->
                <div class="border-t border-border pt-4 mt-4">
                    <h4 class="text-md font-semibold mb-4">Настройки отменённых заказов</h4>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Срок хранения отменённых заказов (дней)</label>
                        <input
                            v-model.number="form.other_settings.canceledOrdersTtlDays"
                            type="number"
                            min="1"
                            max="365"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                        <p class="text-xs text-muted-foreground mt-1">
                            Через указанное количество дней отменённые заказы будут скрыты или удалены
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Действие после истечения срока</label>
                        <select
                            v-model="form.other_settings.canceledOrdersAfterTtlAction"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        >
                            <option value="hide">Скрыть от пользователя</option>
                            <option value="delete">Удалить безвозвратно</option>
                        </select>
                        <p class="text-xs text-muted-foreground mt-1">
                            При "Скрыть" заказы останутся в системе, но не будут видны пользователю. 
                            При "Удалить" заказы будут безвозвратно удалены.
                        </p>
                    </div>
                </div>

                <!-- Настройки уведомлений о неоплаченных заказах -->
                <div class="border-t border-border pt-4 mt-4">
                    <h4 class="text-md font-semibold mb-4">Уведомления о неоплаченных заказах</h4>
                    
                    <div class="mb-4">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.other_settings.unpaidNotificationsEnabled"
                                type="checkbox"
                                class="w-4 h-4"
                            />
                            <span>Включить уведомления о неоплаченных заказах</span>
                        </label>
                        <p class="text-xs text-muted-foreground mt-1 ml-6">
                            Бот будет отправлять уведомления пользователям, если заказ в статусе "Ожидает оплаты" не оплачен
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Отправлять уведомление через (минут)</label>
                        <input
                            v-model.number="form.other_settings.unpaidNotifyAfterMinutes"
                            type="number"
                            min="1"
                            max="1440"
                            class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                        />
                        <p class="text-xs text-muted-foreground mt-1">
                            Через сколько минут после создания заказа отправлять уведомление о необходимости оплаты
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-4">
                <button
                    type="submit"
                    :disabled="saving"
                    class="flex-1 h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl disabled:opacity-50"
                >
                    {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
                </button>
            </div>
        </form>

        <!-- Media Picker Modal для одного файла -->
        <div v-if="showWelcomeMediaPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col">
                <div class="p-6 border-b border-border flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">
                            {{ form.welcome_media_type === 'photo' ? 'Выберите фото' : 'Выберите видео' }}
                        </h3>
                        <button @click="showWelcomeMediaPicker = false" class="text-muted-foreground hover:text-foreground">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
                    <Media
                        :selection-mode="true"
                        :count-file="1"
                        :selected-files="selectedWelcomeMedia ? [{ id: selectedWelcomeMedia.id }] : []"
                        @file-selected="handleWelcomeMediaSelected"
                    />
                </div>
            </div>
        </div>

        <!-- Media Picker Modal для галереи -->
        <div v-if="showWelcomeMediaGalleryPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col">
                <div class="p-6 border-b border-border flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">
                            Выберите фото для галереи (максимум {{ 10 - selectedWelcomeMediaGallery.length }})
                        </h3>
                        <button @click="showWelcomeMediaGalleryPicker = false" class="text-muted-foreground hover:text-foreground">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
                    <Media
                        :selection-mode="true"
                        :count-file="10"
                        :selected-files="selectedWelcomeMediaGallery.map(m => ({ id: m.id }))"
                        @file-selected="handleWelcomeMediaGallerySelected"
                    />
                </div>
            </div>
        </div>

        <!-- Materials Files Picker Modal -->
        <div v-if="showMaterialsPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col">
                <div class="p-6 border-b border-border flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Выберите файлы для отправки</h3>
                        <button @click="showMaterialsPicker = false" class="text-muted-foreground hover:text-foreground">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
                    <Media
                        :selection-mode="true"
                        :count-file="100"
                        :selected-files="selectedMaterialsFiles.map(f => ({ id: f.id }))"
                        @file-selected="handleMaterialsFilesSelected"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPut } from '../../utils/api'
import Swal from 'sweetalert2'
import Media from '../../pages/admin/Media.vue'

export default {
    name: 'BotSettingsForm',
    components: {
        Media,
    },
    props: {
        botId: {
            type: [String, Number],
            required: true,
        },
    },
    emits: ['updated'],
    setup(props, { emit }) {
        const loading = ref(false)
        const saving = ref(false)
        const activeTab = ref('main')
        const tabs = [
            { key: 'main', label: 'Основные' },
            { key: 'messages', label: 'Сообщения' },
            { key: 'advanced', label: 'Дополнительно' },
        ]
        const form = ref({
            required_channel_id: null,
            required_channel_username: '',
            admin_telegram_ids: [],
            yandex_maps_url: '',
            welcome_message: '',
            welcome_media_type: '',
            welcome_media_id: null,
            welcome_media_gallery: [],
            presentation_media_id: null,
            reply_buttons: {
                materials_button_text: '',
                materials_files: [],
                consultation_button_text: '',
                office_button_text: '',
            },
            office_location: {
                latitude: null,
                longitude: null,
                address: '',
            },
            other_settings: {
                phone_validation_strict: false,
                max_description_length: 1000,
                subscription_check_timeout: 5,
                canceledOrdersTtlDays: 7,
                canceledOrdersAfterTtlAction: 'hide',
                unpaidNotificationsEnabled: true,
                unpaidNotifyAfterMinutes: 30,
            },
        })
        
        const showWelcomeMediaPicker = ref(false)
        const showWelcomeMediaGalleryPicker = ref(false)
        const selectedWelcomeMedia = ref(null)
        const selectedWelcomeMediaGallery = ref([])
        const showPresentationPicker = ref(false)
        const selectedPresentationFile = ref(null)
        const showMaterialsPicker = ref(false)
        const selectedMaterialsFiles = ref([])

        const fetchSettings = async () => {
            loading.value = true
            try {
                const response = await apiGet(`/bot-management/${props.botId}/settings`)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки настроек')
                }

                const data = await response.json()
                if (data.success && data.data) {
                    const settings = data.data.settings || {}
                    const messages = settings.messages || {}
                    const welcomeMedia = settings.welcome_media || {}
                    
                    const presentation = settings.presentation || {}
                    const replyButtons = settings.reply_buttons || {}
                    const officeLocation = settings.office_location || {}
                    
                    form.value = {
                        required_channel_id: data.data.required_channel_id || null,
                        required_channel_username: data.data.required_channel_username || '',
                        admin_telegram_ids: data.data.admin_telegram_ids || [],
                        yandex_maps_url: data.data.yandex_maps_url || '',
                        welcome_message: data.data.welcome_message || '',
                        welcome_media_type: welcomeMedia.type || '',
                        welcome_media_id: welcomeMedia.media_id || null,
                        welcome_media_gallery: welcomeMedia.gallery || [],
                        presentation_media_id: presentation.media_id || null,
                        reply_buttons: {
                            materials_button_text: replyButtons.materials_button_text || '',
                            materials_files: replyButtons.materials_files || [],
                            consultation_button_text: replyButtons.consultation_button_text || '',
                            office_button_text: replyButtons.office_button_text || '',
                        },
                        office_location: {
                            latitude: officeLocation.latitude || null,
                            longitude: officeLocation.longitude || null,
                            address: officeLocation.address || '',
                        },
                        messages: {
                            subscription: messages.subscription || {
                                required_text: '',
                                subscribe_button: '',
                                check_button: '',
                            },
                            consultation: messages.consultation || {
                                description: '',
                                form_name_label: '',
                                form_phone_label: '',
                                form_description_label: '',
                                thank_you: '',
                                start_button: '',
                                skip_description_button: '',
                            },
                            materials: messages.materials || {
                                list_description: '',
                                download_button: '',
                                back_to_list: '',
                            },
                            menu: messages.menu || {
                                materials_button: '',
                                consultation_button: '',
                                review_button: '',
                                back_to_menu: '',
                                presentation_button: '',
                            },
                            notifications: messages.notifications || {
                                consultation_template: '',
                            },
                        },
                        other_settings: settings.other_settings || {
                            phone_validation_strict: false,
                            max_description_length: 1000,
                            subscription_check_timeout: 5,
                            canceledOrdersTtlDays: 7,
                            canceledOrdersAfterTtlAction: 'hide',
                            unpaidNotificationsEnabled: true,
                            unpaidNotifyAfterMinutes: 30,
                        },
                    }
                }
            } catch (err) {
                console.error('Error fetching settings:', err)
            } finally {
                loading.value = false
            }
        }

        const saveSettings = async () => {
            saving.value = true
            try {
                const welcomeMedia = {}
                if (form.value.welcome_media_type) {
                    welcomeMedia.type = form.value.welcome_media_type
                    if (form.value.welcome_media_type === 'gallery') {
                        welcomeMedia.gallery = form.value.welcome_media_gallery.map(m => m.id)
                    } else {
                        welcomeMedia.media_id = form.value.welcome_media_id
                    }
                }
                
                const presentation = {}
                if (form.value.presentation_media_id) {
                    presentation.media_id = form.value.presentation_media_id
                }
                
                const replyButtons = {
                    materials_button_text: form.value.reply_buttons.materials_button_text || '',
                    materials_files: form.value.reply_buttons.materials_files.map(f => f.id || f) || [],
                    consultation_button_text: form.value.reply_buttons.consultation_button_text || '',
                    office_button_text: form.value.reply_buttons.office_button_text || '',
                }
                
                const officeLocation = {
                    latitude: form.value.office_location.latitude || null,
                    longitude: form.value.office_location.longitude || null,
                    address: form.value.office_location.address || '',
                }
                
                const response = await apiPut(`/bot-management/${props.botId}/settings`, {
                    required_channel_id: form.value.required_channel_id,
                    required_channel_username: form.value.required_channel_username,
                    admin_telegram_ids: form.value.admin_telegram_ids,
                    yandex_maps_url: form.value.yandex_maps_url,
                    welcome_message: form.value.welcome_message,
                    settings: {
                        messages: form.value.messages,
                        other_settings: form.value.other_settings,
                        welcome_media: welcomeMedia,
                        presentation: presentation,
                        reply_buttons: replyButtons,
                        office_location: officeLocation,
                    },
                })

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка сохранения настроек')
                }

                await Swal.fire({
                    title: 'Сохранено',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                emit('updated')
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения настроек',
                    icon: 'error',
                    confirmButtonText: 'ОК',
                })
            } finally {
                saving.value = false
            }
        }

        const addAdmin = () => {
            form.value.admin_telegram_ids.push(null)
        }

        const removeAdmin = (index) => {
            form.value.admin_telegram_ids.splice(index, 1)
        }

        const handleWelcomeMediaSelected = (file) => {
            if (file) {
                form.value.welcome_media_id = file.id
                selectedWelcomeMedia.value = file
                showWelcomeMediaPicker.value = false
            }
        }

        const removeWelcomeMedia = () => {
            form.value.welcome_media_id = null
            selectedWelcomeMedia.value = null
            if (!form.value.welcome_media_id && !form.value.welcome_media_gallery.length) {
                form.value.welcome_media_type = ''
            }
        }

        const handleWelcomeMediaGallerySelected = (file) => {
            // Компонент Media эмитит один файл за раз
            if (file) {
                // Проверяем, что это фото
                if (file.type !== 'photo' && !file.extension?.match(/^(jpg|jpeg|png|gif|webp)$/i)) {
                    Swal.fire({
                        title: 'Ошибка',
                        text: 'Можно выбрать только фото',
                        icon: 'error',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })
                    return
                }
                
                // Проверяем, не выбран ли уже этот файл
                if (selectedWelcomeMediaGallery.value.some(m => m.id === file.id)) {
                    Swal.fire({
                        title: 'Внимание',
                        text: 'Это фото уже выбрано',
                        icon: 'warning',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })
                    return
                }
                
                // Ограничиваем до 10 фото
                if (selectedWelcomeMediaGallery.value.length >= 10) {
                    Swal.fire({
                        title: 'Внимание',
                        text: 'Можно выбрать максимум 10 фото',
                        icon: 'warning',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })
                    return
                }
                
                selectedWelcomeMediaGallery.value.push(file)
                form.value.welcome_media_gallery = selectedWelcomeMediaGallery.value.map(m => m.id)
            }
        }

        const removeWelcomeMediaFromGallery = (index) => {
            selectedWelcomeMediaGallery.value.splice(index, 1)
            form.value.welcome_media_gallery = selectedWelcomeMediaGallery.value.map(m => ({ id: m.id }))
            if (selectedWelcomeMediaGallery.value.length === 0) {
                form.value.welcome_media_type = ''
            }
        }

        const formatFileSize = (bytes) => {
            if (!bytes) return '0 B'
            const k = 1024
            const sizes = ['B', 'KB', 'MB', 'GB']
            const i = Math.floor(Math.log(bytes) / Math.log(k))
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
        }

        // Загружаем информацию о выбранных медиа при загрузке настроек
        const loadWelcomeMediaInfo = async () => {
            if (form.value.welcome_media_id) {
                try {
                    const response = await apiGet(`/v1/media/${form.value.welcome_media_id}`)
                    if (response.ok) {
                        const data = await response.json()
                        selectedWelcomeMedia.value = data.data || null
                    }
                } catch (err) {
                    console.error('Error loading welcome media:', err)
                }
            }
            
            if (form.value.welcome_media_gallery && form.value.welcome_media_gallery.length > 0) {
                try {
                    // welcome_media_gallery может быть массивом ID или массивом объектов с id
                    const mediaIds = form.value.welcome_media_gallery.map(m => {
                        return typeof m === 'object' && m !== null ? (m.id || m) : m
                    }).filter(id => id !== null && id !== undefined)
                    
                    const promises = mediaIds.map(id => apiGet(`/v1/media/${id}`))
                    const responses = await Promise.all(promises)
                    
                    selectedWelcomeMediaGallery.value = []
                    for (const response of responses) {
                        if (response.ok) {
                            const data = await response.json()
                            if (data.data) {
                                selectedWelcomeMediaGallery.value.push(data.data)
                            }
                        }
                    }
                } catch (err) {
                    console.error('Error loading welcome media gallery:', err)
                }
            }
            
            if (form.value.presentation_media_id) {
                try {
                    const response = await apiGet(`/v1/media/${form.value.presentation_media_id}`)
                    if (response.ok) {
                        const data = await response.json()
                        selectedPresentationFile.value = data.data || null
                    }
                } catch (err) {
                    console.error('Error loading presentation file:', err)
                }
            }
            
            if (form.value.reply_buttons.materials_files && form.value.reply_buttons.materials_files.length > 0) {
                try {
                    const mediaIds = form.value.reply_buttons.materials_files.map(f => {
                        return typeof f === 'object' && f !== null ? (f.id || f) : f
                    }).filter(id => id !== null && id !== undefined)
                    
                    const promises = mediaIds.map(id => apiGet(`/v1/media/${id}`))
                    const responses = await Promise.all(promises)
                    
                    selectedMaterialsFiles.value = []
                    for (const response of responses) {
                        if (response.ok) {
                            const data = await response.json()
                            if (data.data) {
                                selectedMaterialsFiles.value.push(data.data)
                            }
                        }
                    }
                } catch (err) {
                    console.error('Error loading materials files:', err)
                }
            }
        }

        const handlePresentationFileSelected = (file) => {
            if (file) {
                form.value.presentation_media_id = file.id
                selectedPresentationFile.value = file
                showPresentationPicker.value = false
            }
        }

        const removePresentationFile = () => {
            form.value.presentation_media_id = null
            selectedPresentationFile.value = null
        }

        const handleMaterialsFilesSelected = (file) => {
            if (file) {
                // Проверяем, не выбран ли уже этот файл
                if (selectedMaterialsFiles.value.some(f => f.id === file.id)) {
                    Swal.fire({
                        title: 'Внимание',
                        text: 'Этот файл уже выбран',
                        icon: 'warning',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })
                    return
                }
                
                selectedMaterialsFiles.value.push(file)
                form.value.reply_buttons.materials_files = selectedMaterialsFiles.value.map(f => ({ id: f.id }))
            }
        }

        const removeMaterialsFile = (index) => {
            selectedMaterialsFiles.value.splice(index, 1)
            form.value.reply_buttons.materials_files = selectedMaterialsFiles.value.map(f => ({ id: f.id }))
        }

        onMounted(async () => {
            await fetchSettings()
            await loadWelcomeMediaInfo()
        })

        return {
            loading,
            saving,
            activeTab,
            tabs,
            form,
            showWelcomeMediaPicker,
            showWelcomeMediaGalleryPicker,
            selectedWelcomeMedia,
            selectedWelcomeMediaGallery,
            fetchSettings,
            saveSettings,
            addAdmin,
            removeAdmin,
            handleWelcomeMediaSelected,
            removeWelcomeMedia,
            handleWelcomeMediaGallerySelected,
            removeWelcomeMediaFromGallery,
            formatFileSize,
            showPresentationPicker,
            selectedPresentationFile,
            handlePresentationFileSelected,
            removePresentationFile,
            showMaterialsPicker,
            selectedMaterialsFiles,
            handleMaterialsFilesSelected,
            removeMaterialsFile,
        }
    },
}
</script>

