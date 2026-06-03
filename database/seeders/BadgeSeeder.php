<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // [icon, label, color]
        $badges = [
            ['👍', 'Thumbs Up', 'success'],
            ['🤝', 'Team Player', 'info'],
            ['📢', 'Amplifier', 'warning'],
            ['🌟', "You're a Star", 'warning'],
            ['🏆', 'Leadership', 'primary'],
            ['🥇', 'No. 1', 'warning'],
            ['❤️', 'Adore Your Work', 'danger'],
            ['✅', 'Getting Things Done', 'success'],
            ['🎯', 'Smooth Operator', 'info'],
            ['💰', 'Money Maker', 'success'],
            ['💖', 'Kind Heart', 'danger'],
            ['💡', 'Innovator', 'info'],
            ['🙏', 'Thanks', 'primary'],
            ['🤖', "You're a Machine!", 'gray'],
        ];

        foreach ($badges as [$icon, $label, $color]) {
            Badge::updateOrCreate(
                ['label' => $label],
                [
                    'icon' => $icon,
                    'color' => $color,
                    'points' => 10,
                    'is_active' => true,
                ],
            );
        }
    }
}
