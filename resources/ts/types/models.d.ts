/**
 * This file is auto generated using 'php artisan typescript:generate'
 *
 * Changes to this file will be lost when the command is run again
 */

declare namespace App.Models {
  export interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    created_at: string | null;
    updated_at: string | null;
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
    id: number;
    name: string;
    guard_name: string;
    created_at: string | null;
    updated_at: string | null;
    permissions?: Array<App.Models.Permission> | null;
    users?: Array<App.Models.User> | null;
    permissions_count?: number | null;
    users_count?: number | null;


  }

  export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string | null;
    updated_at: string | null;
    roles?: Array<App.Models.Role> | null;
    users?: Array<App.Models.User> | null;
    permissions?: Array<App.Models.Permission> | null;
    roles_count?: number | null;
    users_count?: number | null;
    permissions_count?: number | null;


  }

  export interface Model {
  }

}
