import { createContext, useContext, useState, useEffect, ReactNode } from "react";
import { supabase } from "@/integrations/supabase/client";
import { User as SupabaseUser, Session } from "@supabase/supabase-js";

export interface User {
  id: string;
  email: string;
  name: string;
  phone?: string;
  avatar?: string;
  createdAt: string;
}

export interface ViewedProperty {
  id: string;
  title: string;
  price: number;
  image: string;
  address: string;
  viewedAt: string;
}

export interface Application {
  id: string;
  propertyId: string;
  propertyTitle: string;
  propertyImage: string;
  propertyPrice: number;
  status: "pending" | "viewed" | "accepted" | "rejected" | "cancelled" | "waiting_payment";
  createdAt: string;
  message?: string;
  isHidden?: boolean; // Флаг скрытого заказа (для отменённых заказов старше TTL)
  unpaidNotifiedAt?: string; // Дата отправки уведомления о неоплате (чтобы не спамить)
  telegramUserId?: number; // Telegram user ID для отправки уведомлений через бота
  botId?: number; // ID бота для отправки уведомлений
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<{ success: boolean; error?: string }>;
  register: (email: string, password: string, name: string) => Promise<{ success: boolean; error?: string }>;
  logout: () => Promise<void>;
  updateProfile: (data: Partial<User>) => Promise<void>;
  viewHistory: ViewedProperty[];
  addToViewHistory: (property: Omit<ViewedProperty, "viewedAt">) => void;
  clearViewHistory: () => void;
  applications: Application[];
  addApplication: (application: Omit<Application, "id" | "status" | "createdAt">) => void;
  updateApplicationStatus: (applicationId: string, status: Application["status"]) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Local storage keys for non-sensitive UI state
const VIEW_HISTORY_KEY = "livegrid_view_history";
const APPLICATIONS_KEY = "livegrid_applications";

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [session, setSession] = useState<Session | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [viewHistory, setViewHistory] = useState<ViewedProperty[]>([]);
  const [applications, setApplications] = useState<Application[]>([]);

  // Функция для очистки отмененных заказов по TTL (вызывается извне при загрузке настроек)
  const cleanupCancelledOrders = (ttlDays: number = 7, action: 'hide' | 'delete' = 'hide') => {
    setApplications(prev => {
      const now = new Date();
      const updated = prev.map(app => {
        // Проверяем только отмененные заказы
        if (app.status !== 'cancelled' || app.isHidden) {
          return app;
        }

        const createdAt = new Date(app.createdAt);
        const daysDiff = Math.floor((now.getTime() - createdAt.getTime()) / (1000 * 60 * 60 * 24));

        // Если заказ старше TTL
        if (daysDiff >= ttlDays) {
          if (action === 'delete') {
            return null; // Удаляем
          } else {
            return { ...app, isHidden: true }; // Скрываем
          }
        }

        return app;
      }).filter((app): app is Application => app !== null);

      localStorage.setItem(APPLICATIONS_KEY, JSON.stringify(updated));
      return updated;
    });
  };

  // Fetch user profile from database
  const fetchProfile = async (userId: string) => {
    if (!supabase) return null;
    
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('id', userId)
      .maybeSingle();

    if (error) {
      console.error('Error fetching profile:', error);
      return null;
    }

    if (data) {
      return {
        id: data.id,
        email: data.email,
        name: data.name,
        phone: data.phone || undefined,
        avatar: data.avatar || undefined,
        createdAt: data.created_at,
      } as User;
    }
    return null;
  };

  useEffect(() => {
    if (!supabase) {
      setIsLoading(false);
      return;
    }

    // Set up auth state listener FIRST
    const { data: { subscription } } = supabase.auth.onAuthStateChange(
      (event, session) => {
        setSession(session);
        
        if (session?.user) {
          // Defer profile fetch to avoid deadlock
          setTimeout(() => {
            fetchProfile(session.user.id).then(profile => {
              if (profile) {
                setUser(profile);
              }
            });
          }, 0);
        } else {
          setUser(null);
        }
      }
    );

    // THEN check for existing session
    supabase.auth.getSession().then(({ data: { session } }) => {
      setSession(session);
      if (session?.user) {
        fetchProfile(session.user.id).then(profile => {
          if (profile) {
            setUser(profile);
          }
          setIsLoading(false);
        });
      } else {
        setIsLoading(false);
      }
    });

    // Load view history and applications from localStorage (non-sensitive UI state)
    const savedHistory = localStorage.getItem(VIEW_HISTORY_KEY);
    if (savedHistory) {
      setViewHistory(JSON.parse(savedHistory));
    }

    const savedApplications = localStorage.getItem(APPLICATIONS_KEY);
    if (savedApplications) {
      const parsed = JSON.parse(savedApplications);
      
      // При загрузке проверяем и очищаем отменённые заказы по TTL
      // Используем настройки по умолчанию (7 дней, hide)
      // В реальном приложении эти настройки должны загружаться из API/настроек бота
      const now = new Date();
      const ttlDays = 7; // По умолчанию 7 дней (можно получить из настроек через API)
      const action: 'hide' | 'delete' = 'hide'; // По умолчанию скрывать (можно получить из настроек через API)
      
      const cleaned = parsed.map((app: Application) => {
        if (app.status !== 'cancelled' || app.isHidden) {
          return app;
        }

        const createdAt = new Date(app.createdAt);
        const daysDiff = Math.floor((now.getTime() - createdAt.getTime()) / (1000 * 60 * 60 * 24));

        if (daysDiff >= ttlDays) {
          if (action === 'delete') {
            return null;
          } else {
            return { ...app, isHidden: true };
          }
        }

        return app;
      }).filter((app: Application | null): app is Application => app !== null);

      // Сохраняем очищенные данные
      localStorage.setItem(APPLICATIONS_KEY, JSON.stringify(cleaned));
      setApplications(cleaned);
    }

    return () => subscription.unsubscribe();
  }, []);

  const login = async (email: string, password: string): Promise<{ success: boolean; error?: string }> => {
    if (!supabase) {
      return { success: false, error: "Supabase не настроен. Пожалуйста, настройте переменные окружения." };
    }
    
    try {
      const { data, error } = await supabase.auth.signInWithPassword({
        email: email.toLowerCase().trim(),
        password,
      });

      if (error) {
        // Map Supabase error messages to Russian
        if (error.message.includes('Invalid login credentials')) {
          return { success: false, error: "Неверный email или пароль" };
        }
        if (error.message.includes('Email not confirmed')) {
          return { success: false, error: "Email не подтвержден" };
        }
        return { success: false, error: error.message };
      }

      return { success: true };
    } catch (err) {
      return { success: false, error: "Произошла ошибка при входе" };
    }
  };

  const register = async (email: string, password: string, name: string): Promise<{ success: boolean; error?: string }> => {
    if (!supabase) {
      return { success: false, error: "Supabase не настроен. Пожалуйста, настройте переменные окружения." };
    }
    
    try {
      const redirectUrl = `${window.location.origin}/`;
      
      const { data, error } = await supabase.auth.signUp({
        email: email.toLowerCase().trim(),
        password,
        options: {
          emailRedirectTo: redirectUrl,
          data: {
            name: name.trim(),
          },
        },
      });

      if (error) {
        // Map Supabase error messages to Russian
        if (error.message.includes('User already registered')) {
          return { success: false, error: "Пользователь с таким email уже существует" };
        }
        if (error.message.includes('Password')) {
          return { success: false, error: "Пароль должен быть не менее 6 символов" };
        }
        return { success: false, error: error.message };
      }

      return { success: true };
    } catch (err) {
      return { success: false, error: "Произошла ошибка при регистрации" };
    }
  };

  const logout = async () => {
    if (supabase) {
      await supabase.auth.signOut();
    }
    setUser(null);
    setSession(null);
  };

  const updateProfile = async (data: Partial<User>) => {
    if (!user || !supabase) return;

    const { error } = await supabase
      .from('profiles')
      .update({
        name: data.name,
        phone: data.phone,
        avatar: data.avatar,
        updated_at: new Date().toISOString(),
      })
      .eq('id', user.id);

    if (error) {
      console.error('Error updating profile:', error);
      return;
    }

    setUser({ ...user, ...data });
  };

  const addToViewHistory = (property: Omit<ViewedProperty, "viewedAt">) => {
    setViewHistory(prev => {
      const filtered = prev.filter(p => p.id !== property.id);
      const newHistory = [{ ...property, viewedAt: new Date().toISOString() }, ...filtered].slice(0, 50);
      localStorage.setItem(VIEW_HISTORY_KEY, JSON.stringify(newHistory));
      return newHistory;
    });
  };

  const clearViewHistory = () => {
    setViewHistory([]);
    localStorage.removeItem(VIEW_HISTORY_KEY);
  };

  const addApplication = (application: Omit<Application, "id" | "status" | "createdAt">) => {
    const newApplication: Application = {
      ...application,
      id: crypto.randomUUID(),
      status: "pending",
      createdAt: new Date().toISOString(),
    };

    setApplications(prev => {
      const updated = [newApplication, ...prev];
      localStorage.setItem(APPLICATIONS_KEY, JSON.stringify(updated));
      return updated;
    });
  };

  const updateApplicationStatus = (applicationId: string, status: Application["status"]) => {
    setApplications(prev => {
      const updated = prev.map(app => 
        app.id === applicationId ? { ...app, status } : app
      );
      localStorage.setItem(APPLICATIONS_KEY, JSON.stringify(updated));
      return updated;
    });
  };

  // Фильтруем скрытые заказы при возврате (не показываем скрытые в UI)
  const visibleApplications = applications.filter(app => !app.isHidden);

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user && !!session,
        isLoading,
        login,
        register,
        logout,
        updateProfile,
        viewHistory,
        addToViewHistory,
        clearViewHistory,
        applications: visibleApplications, // Возвращаем только видимые заказы
        addApplication,
        updateApplicationStatus,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
};
