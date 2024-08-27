/**
 * This file is auto generated using 'php artisan typescript:generate'
 *
 * Changes to this file will be lost when the command is run again
 */

declare namespace App.Models {
    export interface User {
        id: any;
        first_name: any;
        last_name: any;
        email: any;
        email_verified_at: any | null;
        password: any;
        created_at: any | null;
        updated_at: any | null;
        notifications?: Array<Illuminate.Notifications.DatabaseNotification> | null;
        read_notifications?: Array<Illuminate.Notifications.DatabaseNotification> | null;
        unread_notifications?: Array<Illuminate.Notifications.DatabaseNotification> | null;
        roles?: Array<App.Models.Role> | null;
        permissions?: Array<App.Models.Permission> | null;
        notifications_count?: number | null;
        read_notifications_count?: number | null;
        unread_notifications_count?: number | null;
        roles_count?: number | null;
        permissions_count?: number | null;
        
        readonly all_permissions?: any;
    }

    export interface Role {
        id: any;
        name: any;
        guard_name: any;
        created_at: any | null;
        updated_at: any | null;
        permissions?: Array<App.Models.Permission> | null;
        users?: Array<App.Models.User> | null;
        permissions_count?: number | null;
        users_count?: number | null;
        
        
    }

    export interface File {
        id: any;
        name: any;
        url: any;
        type: any;
        size: number;
        created_by_id: any | null;
        updated_by_id: any | null;
        deleted_by_id: any | null;
        created_at: any | null;
        updated_at: any | null;
        created_by?: App.Models.User | null;
        updated_by?: App.Models.User | null;
        deleted_by?: App.Models.User | null;
        
        readonly responsive_images?: any;
    }

    export interface Permission {
        id: any;
        name: any;
        guard_name: any;
        created_at: any | null;
        updated_at: any | null;
        roles?: Array<App.Models.Role> | null;
        users?: Array<App.Models.User> | null;
        permissions?: Array<App.Models.Permission> | null;
        roles_count?: number | null;
        users_count?: number | null;
        permissions_count?: number | null;
        
        
    }

}
