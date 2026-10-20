import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';

function currentTheme(): 'light' | 'dark' {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export function ThemeToggle() {
    const [theme, setTheme] = useState<'light' | 'dark'>(currentTheme);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', theme === 'dark');

        try {
            localStorage.setItem('theme', theme);
        } catch {
            // El navegador puede bloquear el almacenamiento; el tema sigue aplicándose en la sesión.
        }
    }, [theme]);

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
            aria-label={theme === 'dark' ? 'Activar tema claro' : 'Activar tema oscuro'}
            title={theme === 'dark' ? 'Tema claro' : 'Tema oscuro'}
        >
            {theme === 'dark' ? <Sun /> : <Moon />}
        </Button>
    );
}
