/**
 * Override Model Definitions from auto generated file
 *
 */

declare namespace App.Models {
  export interface User extends User {
    readonly all_permissions?: Array<Spatie.Permission.Models.Permission>;
  }

  export interface AuthUser extends User {
    all_permissions: Array<Spatie.Permission.Models.Permission>;
  }
}
